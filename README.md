# Gestão de Botijas de Gás

Base Laravel 12 para gestão de botijas de gás. As áreas são **Dashboard**, **Produtos**, **Locais de venda**, **Alocar às bancas**, **Vendas**, **Utilizadores** e **Grupos**. O login por username e password e as permissões por utilizador ou grupo controlam o acesso.

Produtos têm descrição, quantidade e preços do fornecedor, revendedor e unidade. Para receber novo estoque, informe a quantidade recebida na página Produtos; o sistema soma ao saldo e guarda data, utilizador e saldos anterior e posterior. Editar um produto não altera o saldo. Apagar arquiva o produto e mantém os históricos para eventual restauração.

**Vendas a grosso:** ao carregar um camião, registe produto, quantidade, matrícula e motorista. O carregamento já conta como venda e desconta o estoque central. O preço de revendedor e o custo do fornecedor ficam guardados na venda.

**Vendas por unidade:** primeiro aloque botijas do centro para uma banca na página Alocar às bancas. A alocação não altera o estoque total. Depois registe as vendas da banca; cada venda desconta o saldo da banca e o total do produto. O preço por unidade e o custo ficam guardados na venda. O sistema impede alocações e vendas acima do saldo disponível.

No Dashboard, o investimento é quantidade atual × custo atual do fornecedor. Os valores de venda e margem estão separados por grosso e unidade, incluindo os valores de hoje. A margem histórica usa o preço e custo guardados no momento da venda.

Na página Utilizadores pode atribuir um posto: vendedores de bancas ficam limitados à própria banca e utilizadores associados a carros só podem registar vendas a grosso com a matrícula desse carro. Gestores sem posto atribuído podem operar em ambos os fluxos, conforme as permissões. As páginas de edição de utilizadores e grupos permitem atribuir as novas permissões aos registos já existentes.

## Iniciar

Requer PHP 8.2+, Composer e SQLite.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan access:bootstrap
php artisan serve
```

Abra `http://127.0.0.1:8000`. O comando `access:bootstrap` cria o administrador principal e pede uma password. Se o banco já existir, execute `php artisan migrate --seed` para acrescentar as novas tabelas e permissões. Os registos existentes são conservados.

Para executar os testes: `php artisan test`.
