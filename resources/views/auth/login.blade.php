<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102831">
    <title>Entrar · Botija POS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-body">
    <aside class="login-showcase">
        <div class="brand"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 22c-5 0-8-3.4-8-7.6 0-3.3 2-5.9 4.7-8.5-.1 3 1.6 4.3 1.6 4.3C11 6.8 13.5 4.2 15 2c.4 3.8 5 6.7 5 12.4 0 4.2-3 7.6-8 7.6Z"/></svg></span><span class="brand-copy"><strong>botija<span>POS</span></strong><small>Gestão de gás</small></span></div>
        <div><h2>O seu negócio,<br><span>sempre em movimento.</span></h2><p>Produtos, estoque, bancas e vendas reunidos num só lugar para uma operação simples e organizada.</p></div>
        <div class="login-showcase-footer">Gestão de botijas de gás · POS</div>
    </aside>
    <main class="login-main">
        <section class="login-card" aria-labelledby="login-title">
            <span class="login-eyebrow">BEM-VINDO DE VOLTA</span>
            <h1 id="login-title">Entrar na conta</h1>
            <p>Utilize o seu username e password para aceder ao sistema.</p>
            @if($errors->any())<div class="alert error" role="alert"><span class="alert-symbol">!</span><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="post" action="{{ route('login.store') }}">@csrf
                <label>Username<input name="username" value="{{ old('username') }}" autocomplete="username" placeholder="O seu username" required autofocus></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" placeholder="A sua password" required></label>
                <button type="submit">Entrar no sistema <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
</body>
</html>
