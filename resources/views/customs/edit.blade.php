@extends('layouts.app')
@section('title', 'Editar processo aduaneiro')
@section('content')
<span class="section-eyebrow">Processos Aduaneiros</span>
<h2 class="page-title">Editar {{ $process->display_reference }}</h2>
<p class="page-subtitle">Actualize o estágio, os documentos e os dados do processo.</p>
<a class="small-link" href="{{ route('customs.timeline', $process) }}">Ver linha do tempo</a>
<section class="panel customs-form-panel">
    <form method="post" action="{{ route('customs.update', $process) }}" enctype="multipart/form-data">@csrf @method('PATCH')
        @include('customs.form')
        <div class="form-actions"><button type="submit">Guardar alterações</button><a href="{{ route('customs.index') }}">Cancelar</a></div>
    </form>
</section>
@endsection
