<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index()
    {
        return view('customers.index', [
            'customers' => Customer::with('contacts')->orderBy('name')->paginate(25),
            'totalCustomers' => Customer::count(),
            'activeCustomers' => Customer::where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        

        DB::transaction(function () use ($data, $request) {
            $customer = Customer::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'nuit' => $data['nuit'],
                'physical_address' => $data['physical_address'],
                'is_active' => $request->boolean('is_active'),
            ]);
            $customer->contacts()->createMany($data['contacts']);
        });

        return redirect()->route('customers.index')->with('success', 'Cliente registado.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', ['customer' => $customer->load('contacts')]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($customer, $data, $request) {
            $customer->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'nuit' => $data['nuit'],
                'physical_address' => $data['physical_address'],
                'is_active' => $request->boolean('is_active'),
            ]);
            $customer->contacts()->delete();
            $customer->contacts()->createMany($data['contacts']);
        });

        return redirect()->route('customers.index')->with('success', 'Cliente atualizado.');
    }

    public function toggleActive(Customer $customer)
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with('success', $customer->is_active ? 'Cliente activado.' : 'Cliente desactivado.');
    }

    private function validatedData(Request $request): array
    {
        $request->merge(['nuit' => trim((string) $request->input('nuit'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['person', 'organization'])],
            'nuit' => ['required', 'string', 'max:30', Rule::unique('customers', 'nuit')->ignore($request->route('customer')?->id)],
            'physical_address' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'contacts' => ['required', 'array', 'min:1', 'max:30'],
            'contacts.*.type' => ['required', Rule::in(['email', 'phone', 'mobile'])],
            'contacts.*.value' => ['required', 'string', 'max:255'],
        ]);

        foreach ($data['contacts'] as $index => $contact) {
            if ($contact['type'] === 'email' && ! filter_var($contact['value'], FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(["contacts.$index.value" => 'Introduza um email válido.']);
            }
            if ($contact['type'] !== 'email' && ! preg_match('/^\+?[0-9() .-]{6,30}$/', $contact['value'])) {
                throw ValidationException::withMessages(["contacts.$index.value" => 'Introduza um número de contacto válido.']);
            }
        }

        return $data;
    }
}
