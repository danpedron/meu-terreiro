# Meu Terreiro

Plataforma administrativa web para organização de terreiros de Umbanda e comunidades afro-brasileiras. O projeto foi pensado para ser simples o suficiente para o uso cotidiano por dirigentes, regentes, secretários e pessoas mais velhas, sem abrir mão de uma base técnica preparada para evolução enterprise.

> **Estado atual:** MVP em evolução. O repositório contém o núcleo de autenticação, o isolamento lógico por terreiro, o dashboard e o primeiro módulo de cadastro de filhos de santo. Os demais módulos estão documentados no roadmap e serão incorporados gradualmente.

## Visão do projeto

O **Meu Terreiro** busca apoiar a administração da casa de axé sem substituir os seus fundamentos espirituais, sua tradição ou a autoridade de seus dirigentes. A ferramenta organiza informações operacionais, preservando o cuidado com dados pessoais, registros rituais e conhecimentos que devem permanecer sob responsabilidade de cada terreiro.

O sistema foi desenhado com uma linguagem visual inspirada em referências afro-brasileiras de maneira respeitosa: tons de terra e barro lembram o vínculo com o chão e a ancestralidade; dourado representa cuidado e prosperidade; verde remete às folhas e à natureza; e o índigo comunica acolhimento e profundidade. A interface usa esses elementos como paleta e hierarquia visual, sem transformar símbolos sagrados em decoração genérica.

## Funcionalidades previstas

| Área | Objetivo |
|---|---|
| Terreiro | Cadastro de nome, localização, fundação, nação, regência e informações institucionais. |
| Pessoas | Cadastro de filhos de santo, regentes, babalorixás, yalorixás, ogãs, ekedis e demais funções. |
| Entidades | Registro de entidades, características, cores de vela, pontos riscados e recados autorizados. |
| Obrigações | Catálogo de rituais e histórico das obrigações realizadas por cada filho. |
| Agenda | Organização de giras, obrigações, festas, reuniões e outros compromissos. |
| Biblioteca | Catálogo de livros e materiais, incluindo links controlados para arquivos do Google Drive. |
| Financeiro | Definição da mensalidade, registro de pagamentos, acompanhamento de pendências e relatórios. |
| Exportação | Exportação controlada dos dados do próprio filho para importação em outro terreiro, mediante autorização. |

## Arquitetura multi-tenant

Cada terreiro deve ser tratado como uma unidade independente. A arquitetura proposta usa um banco central para autenticação e catálogo de tenants e um banco separado para os dados de cada terreiro.

```text
meuterreiro_admin
├── tenants       -> cadastro técnico dos terreiros
└── users         -> usuários e papéis de acesso

meuterreiro_<slug>
├── terreiro_info
├── filhos
├── entidades
├── obrigacoes_tipo
├── filhos_obrigacoes
├── agenda
├── mensalidades
└── biblioteca
```

O código nunca deve decidir o banco do tenant a partir de texto recebido diretamente da URL. O `TenantManager` consulta o slug no banco central, verifica o status do tenant e só então abre a conexão para o banco associado. Em uma instalação de produção, a conta usada pela aplicação deve possuir apenas os privilégios necessários e as rotinas de provisionamento de bancos devem permanecer protegidas para a equipe responsável pela infraestrutura.

## Requisitos

A aplicação utiliza PHP com PDO e MariaDB. Para desenvolvimento local, recomenda-se PHP 8.2 ou superior, MariaDB 10.6 ou superior, Nginx ou Apache com PHP-FPM, Composer quando novas dependências forem adicionadas e Git.

Consulte a documentação oficial do [PHP](https://www.php.net/docs.php), do [PDO](https://www.php.net/manual/en/book.pdo.php), do [MariaDB](https://mariadb.com/kb/en/documentation/) e do [PHP-FPM](https://www.php.net/manual/en/install.fpm.php) para preparar o ambiente.

## Instalação local

### 1. Clonar o repositório

```bash
git clone https://github.com/SEU_USUARIO/meu-terreiro.git
cd meu-terreiro
```

### 2. Configurar as credenciais fora do Git

Copie o exemplo para um arquivo local e substitua os valores somente na sua máquina ou no ambiente de hospedagem:

```bash
cp config/db_config.local.php.example config/db_config.local.php
chmod 640 config/db_config.local.php
```

O arquivo `config/db_config.local.php` está no `.gitignore` e **nunca deve ser enviado ao GitHub**. Como alternativa, use variáveis de ambiente:

```bash
export MEUTERREIRO_DB_HOST=127.0.0.1
export MEUTERREIRO_DB_NAME=meuterreiro_admin
export MEUTERREIRO_DB_USER=meuterreiro_app
export MEUTERREIRO_DB_PASS='defina-uma-senha-forte-fora-do-repositorio'
```

Não use os valores deste exemplo em produção. Cada instalação deve gerar sua própria credencial forte e armazená-la em um cofre de segredos ou em configuração protegida do servidor.

### 3. Criar os bancos

O banco central é criado com `database/central_schema.sql`. Para cada terreiro, crie um banco próprio e aplique `database/terreiro_schema.sql`:

```bash
mysql -u root -p < database/central_schema.sql
mysql -u root -p -e "CREATE DATABASE meuterreiro_principal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p meuterreiro_principal < database/terreiro_schema.sql
```

Crie um usuário de aplicação com senha definida somente no ambiente. Em produção, avalie cuidadosamente os privilégios concedidos; o exemplo abaixo é um ponto de partida para uma instalação de desenvolvimento e não deve ser copiado sem revisão:

```sql
CREATE USER 'meuterreiro_app'@'localhost' IDENTIFIED BY 'defina-uma-senha-forte-fora-do-repositorio';
GRANT SELECT, INSERT, UPDATE, DELETE ON meuterreiro_admin.* TO 'meuterreiro_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON meuterreiro_principal.* TO 'meuterreiro_app'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Publicar o diretório público

A raiz pública do servidor web deve apontar para o diretório `public/`. Os diretórios `config/`, `modules/` e `database/` não devem ser acessíveis como arquivos estáticos pela internet.

Exemplo conceitual de Nginx para desenvolvimento, usando um domínio fictício:

```nginx
server {
    listen 80;
    server_name terreiro.exemplo.test;
    root /var/www/meu-terreiro/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /(?:config|modules|database)/ {
        deny all;
    }
}
```

Antes de aplicar qualquer alteração no Nginx de um servidor que hospede outros sites, faça backup do arquivo específico, execute `nginx -t` e confirme o escopo do virtual host. Nunca substitua a configuração global sem necessidade.

## Primeiro acesso

Após criar um usuário no banco central, abra o endereço configurado para a instalação e use a tela inicial do sistema. O endereço `public/auth.php` é um endpoint de formulário e deve ser acessado por `POST`; a entrada normal do usuário é `public/index.php`.

As credenciais iniciais não são fornecidas pelo repositório. Cada administrador deve criá-las durante a instalação e alterar qualquer senha temporária imediatamente.

## Segurança e privacidade

O projeto lida potencialmente com CPF, endereço, telefone, datas pessoais, registros espirituais e informações financeiras. Por isso, uma instalação real deve aplicar HTTPS, controle de acesso por papel, backups criptografados, retenção mínima de dados, trilhas de auditoria e política de consentimento compatível com a legislação aplicável.

Nunca faça commit de senhas, tokens, chaves privadas, dumps de banco, cookies, arquivos `.env`, logs de produção ou URLs internas. Antes de cada push, execute uma busca por padrões sensíveis e revise o diff:

```bash
git diff --cached --check
git grep -nE -i 'password|secret|api[_-]?key|BEGIN (OPENSSH|RSA|EC) PRIVATE KEY|\.local\.php' -- . ':!vendor' || true
git status --short
```

Se uma credencial for exposta, considere-a comprometida: revogue-a, gere outra e investigue o histórico do Git. Remover o texto do arquivo atual não remove o segredo dos commits antigos.

## Design e acessibilidade

A interface utiliza contraste elevado, fontes legíveis, áreas de toque amplas, navegação simplificada, mensagens de erro claras e adaptação para telas pequenas. A meta é que o sistema funcione como um webapp em celulares, inclusive em aparelhos mais antigos.

As decisões visuais devem preservar o respeito às tradições afro-brasileiras. A paleta pode ser ampliada por cada terreiro, mas recomenda-se evitar apropriação de símbolos litúrgicos, imagens de entidades sem autorização ou uso de elementos sagrados como ornamentos arbitrários.

## Desenvolvimento e contribuição

Contribuições são bem-vindas. Antes de abrir uma issue, verifique se o problema já foi registrado. Para propor uma mudança:

```bash
git checkout -b feat/minha-melhoria
# implemente e teste a mudança
git add .
git commit -m "feat: descreve a melhoria de forma objetiva"
git push origin feat/minha-melhoria
```

Use mensagens de commit claras, preferencialmente no formato Conventional Commits:

| Prefixo | Uso |
|---|---|
| `feat:` | Nova funcionalidade. |
| `fix:` | Correção de defeito. |
| `docs:` | Alteração de documentação. |
| `refactor:` | Refatoração sem mudança de comportamento. |
| `security:` | Correção ou endurecimento de segurança. |
| `chore:` | Manutenção e ferramentas. |

Toda alteração deste projeto deve ser registrada em um commit que explique o que foi modificado e enviada ao GitHub. Pull requests devem informar o contexto, o impacto, os testes realizados e qualquer mudança de banco ou configuração de servidor. Consulte [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md) e [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Roadmap

As próximas etapas incluem o cadastro completo do terreiro, perfis de dirigentes, entidades e pontos riscados com controle de acesso, obrigações, agenda de giras, biblioteca, mensalidades, exportação autorizada e trilha de auditoria. Cada módulo será implementado com atenção à separação entre dados de terreiros e à experiência de uso em dispositivos móveis.

## Licença

Este projeto é distribuído sob a licença MIT. Consulte [LICENSE](LICENSE).

## Referências

[1]: https://www.php.net/docs.php "Documentação oficial do PHP"
[2]: https://www.php.net/manual/en/book.pdo.php "Documentação oficial do PDO"
[3]: https://mariadb.com/kb/en/documentation/ "Documentação oficial do MariaDB"
[4]: https://www.php.net/manual/en/install.fpm.php "Documentação oficial do PHP-FPM"
