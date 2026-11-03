@extends('layouts.app')

@section('title', 'Iniciar processo aduaneiro')

@section('content')
    <span class="section-eyebrow">Processos Aduaneiros</span>

    <h2 class="page-title">Iniciar processo aduaneiro</h2>

    <p class="page-subtitle">
        Indique o cliente, o técnico despachante responsável e os requerimentos.
        Depois poderá adicionar várias tarefas ao processo.
    </p>

    <section class="panel customs-form-panel">
        <form method="post"
              action="{{ route('customs.store') }}"
              enctype="multipart/form-data">

            @csrf

            <div class="fields">
                @include('customs.importer-picker')

                @include('customs.responsible-picker')
            </div>

            {{-- Campo de observações --}}
            <div class="customs-observations">
                <label for="observations">
                    Observações
                </label>

                <textarea
                    name="observations"
                    id="observations"
                    rows="4"
                    placeholder="Introduza observações ou informações adicionais sobre o processo..."
                >{{ old('observations') }}</textarea>

                @error('observations')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="customs-start-documents">
                <label>
                    Requerimentos para as alfândegas

                    <input
                        type="file"
                        name="documents[]"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        
                    >
                </label>

                <small class="form-help">
                    Selecione pelo menos um requerimento. Pode anexar até
                    10 documentos por envio; o limite da aplicação é 10 MB
                    por ficheiro e o servidor pode impor um limite inferior.
                </small>
            </div>

            <div class="form-actions">
                <button type="submit">Iniciar processo</button>

                <a href="{{ route('customs.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script
        src="{{ asset('js/customs-searchable-select.js') }}?v={{ filemtime(public_path('js/customs-searchable-select.js')) }}">
    </script>
@endpush
