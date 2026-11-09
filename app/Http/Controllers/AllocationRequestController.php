<?php

namespace App\Http\Controllers;

use App\Models\AllocationRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductAllocation;
use App\Models\ProductStandStock;
use App\Services\AllocationExitPdf;
use App\Services\InventoryBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AllocationRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny($request);
        $point = $request->user()->is_super_admin ? null : $request->user()->salesLocation;
        $canManageAll = $request->user()->is_super_admin
            || $request->user()->hasPermission('allocations.approve')
            || $request->user()->hasPermission('allocations.dispatch');
        $query = AllocationRequest::with(['items.product', 'location', 'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy']);
        if (! $canManageAll) {
            $query->where(function ($query) use ($point, $request) {
                if ($point) $query->where('location_id', $point->id)->orWhere('requested_by_user_id', $request->user()->id);
                else $query->where('requested_by_user_id', $request->user()->id);
            });
        }

        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return view('allocations.requests', [
            'requests' => $query->latest('id')->paginate(25),
            'products' => Product::orderBy('brand')->orderBy('weight_kg')->get()->filter(fn (Product $product) => $product->isReadyForSale()),
            'stands' => Location::where('is_sales_point', true)->where('kind', 'physical')->where('is_active', true)->orderBy('name')->get(),
            'assignedPoint' => $point,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('allocations.request'), 403);
        $point = $request->user()->is_super_admin ? null : $request->user()->salesLocation;
        abort_if($point && $point->kind !== 'physical', 403);
        $data = $request->validate([
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('is_sales_point', true)->where('kind', 'physical')->where('is_active', true)],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($point && filled($data['location_id'] ?? null) && (int) $data['location_id'] !== $point->id) {
            throw ValidationException::withMessages(['location_id' => 'Só pode solicitar botijas para o seu posto.']);
        }
        $locationId = $point?->id ?? ($data['location_id'] ?? null);
        if (! $locationId) throw ValidationException::withMessages(['location_id' => 'Escolha a banca de destino.']);
        if (! Location::whereKey($locationId)->where('kind', 'physical')->where('is_sales_point', true)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['location_id' => 'Este local está inativo. Escolha um posto ativo.']);
        }

        $quantities = collect($data['quantities'])->filter(fn ($quantity) => (int) $quantity > 0);
        if ($quantities->isEmpty()) throw ValidationException::withMessages(['quantities' => 'Informe a quantidade de pelo menos um produto.']);
        $products = Product::whereIn('id', $quantities->keys())->get()->keyBy('id');
        foreach ($quantities as $productId => $quantity) {
            if (! $products->get($productId)?->isReadyForSale()) {
                throw ValidationException::withMessages(['quantities' => 'Escolha apenas produtos ativos com preços completos.']);
            }
        }

        DB::transaction(function () use ($request, $data, $locationId, $quantities) {
            $allocationRequest = AllocationRequest::create([
                'location_id' => $locationId, 'requested_by_user_id' => $request->user()->id,
                'status' => 'pending', 'note' => $data['note'] ?? null,
            ]);
            foreach ($quantities as $productId => $quantity) {
                $allocationRequest->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
            }
        });

        return redirect()->route('allocation-requests.index')->with('success', 'Pedido de botijas registado para aprovação.');
    }

    public function approve(Request $request, AllocationRequest $allocationRequest)
    {
        abort_unless($request->user()->hasPermission('allocations.approve'), 403);
        $this->preventSelfApproval($request, $allocationRequest);
        DB::transaction(function () use ($request, $allocationRequest) {
            $allocationRequest = AllocationRequest::whereKey($allocationRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($allocationRequest->status === 'pending', 409);
            if (! $allocationRequest->location()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['location_id' => 'Ative o local antes de aprovar este pedido.']);
            }
            $allocationRequest->update(['status' => 'approved', 'approved_by_user_id' => $request->user()->id, 'approved_at' => now()]);
        });

        return back()->with('success', 'Pedido aprovado. Registe a saída para gerar a guia.');
    }

    public function reject(Request $request, AllocationRequest $allocationRequest)
    {
        abort_unless($request->user()->hasPermission('allocations.approve'), 403);
        $this->preventSelfApproval($request, $allocationRequest);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $allocationRequest, $data) {
            $allocationRequest = AllocationRequest::whereKey($allocationRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($allocationRequest->status === 'pending', 409);
            $allocationRequest->update([
                'status' => 'rejected', 'rejected_by_user_id' => $request->user()->id,
                'rejected_at' => now(), 'rejection_reason' => $data['rejection_reason'],
            ]);
        });

        return back()->with('success', 'Pedido rejeitado.');
    }

    public function dispatch(Request $request, AllocationRequest $allocationRequest, InventoryBalances $balances)
    {
        abort_unless($request->user()->hasPermission('allocations.dispatch'), 403);
        $data = $request->validate([
            'truck_plate' => ['required', 'string', 'max:30'],
            'driver_name' => ['required', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($request, $allocationRequest, $balances, $data) {
            $allocationRequest = AllocationRequest::with('items')->whereKey($allocationRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($allocationRequest->status === 'approved', 409);
            if (! $allocationRequest->location()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['location_id' => 'Ative o local antes de registar a saída.']);
            }
            foreach ($allocationRequest->items->sortBy('product_id') as $item) {
                $product = $balances->lockProduct($item->product_id);
                if (! $product->isReadyForSale() || $balances->centralQuantity($product) < $item->quantity) {
                    throw ValidationException::withMessages(['stock' => "Estoque central insuficiente para {$product->label}."]);
                }
            }
            $allocationRequest->update([
                'status' => 'in_transit', 'truck_plate' => mb_strtoupper(trim($data['truck_plate'])),
                'driver_name' => trim($data['driver_name']),
                'dispatched_by_user_id' => $request->user()->id, 'dispatched_at' => now(),
            ]);
        });

        return redirect()->route('allocation-requests.guide', $allocationRequest);
    }

    public function guide(Request $request, AllocationRequest $allocationRequest, AllocationExitPdf $pdf)
    {
        $this->authorizeView($request, $allocationRequest);
        abort_unless(in_array($allocationRequest->status, ['in_transit', 'received'], true), 404);

        return response($pdf->render($allocationRequest->load(['items.product', 'location', 'requestedBy', 'approvedBy', 'dispatchedBy'])), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="guia-saida-'.str_pad((string) $allocationRequest->id, 6, '0', STR_PAD_LEFT).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function receive(Request $request, AllocationRequest $allocationRequest, InventoryBalances $balances)
    {
        abort_unless($request->user()->hasPermission('allocations.receive'), 403);
        $this->authorizeLocation($request, $allocationRequest);
        $request->validate(['driver_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $file = $request->file('driver_document');
        $path = $file->store('allocation-receipts', 'local');
        if (! $path) throw new RuntimeException('Não foi possível guardar o comprovativo do motorista.');

        try {
            DB::transaction(function () use ($request, $allocationRequest, $balances, $path, $file) {
                $allocationRequest = AllocationRequest::with('items')->whereKey($allocationRequest->id)->lockForUpdate()->firstOrFail();
                abort_unless($allocationRequest->status === 'in_transit', 409);
                foreach ($allocationRequest->items->sortBy('product_id') as $item) {
                    $balances->lockProduct($item->product_id);
                    ProductStandStock::firstOrCreate(
                        ['product_id' => $item->product_id, 'location_id' => $allocationRequest->location_id],
                        ['quantity' => 0]
                    )->increment('quantity', $item->quantity);
                    ProductAllocation::create([
                        'product_id' => $item->product_id, 'location_id' => $allocationRequest->location_id,
                        'quantity' => $item->quantity, 'created_by_user_id' => $request->user()->id,
                        'note' => 'Receção do pedido #'.$allocationRequest->id,
                    ]);
                }
                $allocationRequest->update([
                    'status' => 'received', 'received_by_user_id' => $request->user()->id,
                    'received_at' => now(), 'driver_document_path' => $path,
                    'driver_document_name' => basename($file->getClientOriginalName()),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('allocation-requests.index')->with('success', 'Entrega confirmada com comprovativo do motorista. Estoque da banca atualizado.');
    }

    public function driverDocument(Request $request, AllocationRequest $allocationRequest)
    {
        $this->authorizeView($request, $allocationRequest);
        abort_unless($allocationRequest->status === 'received' && $allocationRequest->driver_document_path
            && Storage::disk('local')->exists($allocationRequest->driver_document_path), 404);

        return Storage::disk('local')->download($allocationRequest->driver_document_path, $allocationRequest->driver_document_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeAny(Request $request): void
    {
        abort_unless(collect(['allocations.request', 'allocations.approve', 'allocations.dispatch', 'allocations.receive'])
            ->contains(fn ($permission) => $request->user()->hasPermission($permission)), 403);
    }

    private function authorizeView(Request $request, AllocationRequest $allocationRequest): void
    {
        $this->authorizeAny($request);
        if ($allocationRequest->requested_by_user_id === $request->user()->id) return;
        $this->authorizeLocation($request, $allocationRequest);
    }

    private function authorizeLocation(Request $request, AllocationRequest $allocationRequest): void
    {
        if ($request->user()->is_super_admin || $request->user()->hasPermission('allocations.approve')
            || $request->user()->hasPermission('allocations.dispatch')) return;
        $point = $request->user()->salesLocation;
        abort_unless($point && $point->id === $allocationRequest->location_id, 403);
    }

    private function preventSelfApproval(Request $request, AllocationRequest $allocationRequest): void
    {
        abort_if(! $request->user()->is_super_admin && $allocationRequest->requested_by_user_id === $request->user()->id, 403);
    }
}
