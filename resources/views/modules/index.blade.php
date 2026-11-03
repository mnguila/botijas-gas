@extends('layouts.app')
@section('title', 'Módulos')
@section('content')
<span class="section-eyebrow">Área de trabalho</span>
<h2 class="page-title">Módulos</h2>
<p class="page-subtitle">Escolha a área em que pretende trabalhar.</p>

<div class="modules-grid">
    @foreach($modules as $index => $module)
        <article class="module-card">
            <div class="module-card-top">
                <span class="module-card-icon">
                    <svg>
                        <use href="#{{ $module['icon'] }}"/>
                    </svg>
                </span>
                <span class="module-card-number">MÓDULO {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
            </div>
            <h3>{{ $module['name'] }}</h3>
            <p>{{ $module['description'] }}</p>
            @if($module['entry_url'])
                <a class="module-card-action" href="{{ $module['entry_url'] }}">Abrir módulo <span aria-hidden="true">→</span></a>
            @else
                <span class="module-card-locked">Sem acesso atribuído</span>
            @endif
        </article>
    @endforeach
</div>
@endsection
