# Meu Terreiro

Plataforma administrativa web para organização de terreiros de Umbanda e comunidades afro-brasileiras. O projeto foi pensado para ser simples o suficiente para o uso cotidiano por dirigentes, regentes, secretários e pessoas mais velhas, sem abrir mão de uma base técnica preparada para evolução enterprise.

> **Estado atual:** plataforma funcional em evolução. O repositório contém contas globais de participantes, criação comunitária de novas casas, isolamento por banco, diretório público por localização consentida, solicitações de vínculo e consulta, aprovação de dirigência, autenticação por perfis, módulos operacionais, estoque, financeiro, memória e portabilidade autorizada de dados pessoais entre casas.

## Visão do projeto

O **Meu Terreiro** busca apoiar a administração da casa de axé sem substituir os seus fundamentos espirituais, sua tradição ou a autoridade de seus dirigentes. A ferramenta organiza informações operacionais, preservando o cuidado com dados pessoais, registros rituais e conhecimentos que devem permanecer sob responsabilidade de cada terreiro.

O sistema foi desenhado com uma linguagem visual inspirada em referências afro-brasileiras de maneira respeitosa: tons de terra e barro lembram o vínculo com o chão e a ancestralidade; dourado representa cuidado e prosperidade; verde remete às folhas e à natureza; e o índigo comunica acolhimento e profundidade. A interface usa esses elementos como paleta e hierarquia visual, sem transformar símbolos sagrados em decoração genérica.

## Funcionalidades previstas

| Área | Objetivo |
|---|---|
| Casa | Cadastro de nome, localização, fundação, nação, Babalorixá, Yalorixá, mensalidade sugerida e informações institucionais. |
| Pessoas | Cadastro de filhos de santo, funções na casa e situação de participação. |
| Entidades e obrigações | Características autorizadas, ponto riscado por link privado, recados, catálogo de obrigações e histórico individual com sigilo. |
| Agenda e rotina | Giras, obrigações, festas, estudos, reuniões, tarefas de limpeza, manutenção, cozinha e comunicados internos. |
| Financeiro | Mensalidades, entradas, saídas, meios de pagamento, comprovantes por link e visibilidade controlada. |
| Estoque e compras | Folhas e ervas, velas, alimentos, produtos de limpeza, materiais, fornecedores, inventário e alertas de estoque mínimo. |
| Preparo e oferendas | Registros administrativos reservados, com campo para cuidados ambientais e sem prescrever fundamento ritual. |
| Memória e estudo | Biblioteca, materiais em nuvem, álbum de fotos com consentimento de imagem e patrimônio da casa. |
| Locais e cuidado | Referências privadas para folhas, ervas, encruzilhadas e outros locais, com condição de acesso e restrição padrão. |
| Segurança | Registro sigiloso de ocorrências, providências e contatos de apoio. |
| Portabilidade | Exportação em JSON e importação controlada de cadastro, entidades e histórico de obrigações do próprio filho, com confirmação explícita, auditoria e isolamento entre bancos. |
| Comunidade | Qualquer pessoa pode criar uma conta, criar uma casa, solicitar vínculo como consulente, assistência, filho de santo, Babalorixá ou Yalorixá e escolher uma casa aprovada para acessar. |
| Diretório público | Busca por cidade ou localização consentida, página pública da casa, horários de gira, dirigente, informações de presença autorizadas, canais de contato e solicitação de consulta. |
| Governança | Lideranças locais aprovam vínculos cotidianos. Pedidos de Babalorixá/Yalorixá em casa sem dirigente verificado seguem para a administração global, única com acesso de supervisão a todas as casas. |

## Arquitetura multi-tenant

Cada terreiro deve ser tratado como uma unidade independente. A arquitetura proposta usa um banco central para autenticação e catálogo de tenants e um banco separado para os dados de cada terreiro.

```text
meuterreiro_admin
├── tenants                      -> cadastro técnico e perfil público opcional das casas
├── users                        -> contas globais de participantes e administração global
├── tenant_memberships           -> vínculos, papéis e fluxo de aprovação por casa
├── tenant_consultation_requests -> pedidos de consulta com consentimento
└── central_audit_log            -> trilha de auditoria da camada comunitária

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

O código nunca deve decidir o banco do tenant a partir de texto recebido diretamente da URL. O `TenantManager` consulta o slug no banco central, verifica o status do tenant e só então abre a conexão para o banco associado. A aplicação diária usa uma conta limitada; o onboarding usa uma conta separada de provisionamento apenas para criar o banco isolado e aplicar o schema inicial. Ambas as credenciais ficam fora do Git.

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
export MEUTERREIRO_PROVISIONER_DB_USER='meuterreiro_provisioner'
export MEUTERREIRO_PROVISIONER_DB_PASS='defina-outra-senha-forte-fora-do-repositorio'
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
GRANT SELECT, INSERT, UPDATE, DELETE ON `meuterreiro\\_%`.* TO 'meuterreiro_app'@'localhost';

-- O provisionador é usado somente pelo cadastro de uma nova casa.
CREATE USER 'meuterreiro_provisioner'@'localhost' IDENTIFIED BY 'defina-outra-senha-forte-fora-do-repositorio';
GRANT CREATE ON *.* TO 'meuterreiro_provisioner'@'localhost';
GRANT ALL PRIVILEGES ON `meuterreiro\\_%`.* TO 'meuterreiro_provisioner'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Aplicar migrations em instalações existentes

Instalações já criadas antes da expansão devem aplicar as migrations primeiro. Execute a migration central uma vez e a migration de tenant uma vez para cada banco isolado, sempre após backup e em uma janela de manutenção:

```bash
mysql -u root -p meuterreiro_admin < database/migrations/001_central_onboarding.sql
mysql -u root -p meuterreiro_admin < database/migrations/002_comunidade_central.sql
mysql -u root -p meuterreiro_principal < database/migrations/001_tenant_administracao.sql
```

### 5. Publicar o diretório público

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

A entrada normal do usuário é `public/index.php`. Qualquer pessoa pode criar uma conta global e, depois, solicitar vínculo a uma casa ou criar uma nova casa. A criação não concede dirigência automaticamente: pedidos de Babalorixá ou Yalorixá em uma casa sem dirigente verificado exigem liberação da administração global.

O diretório público está em `public/directory.php`. A busca por geolocalização só é executada após ação explícita da pessoa e usa a posição somente naquela consulta; nenhuma coordenada da pessoa é persistida nem enviada às casas. Casas decidem se entram no diretório, quais dados exibem e se mostram localização aproximada ou detalhada.

As credenciais iniciais não são fornecidas pelo repositório. Cada administrador deve criá-las durante a instalação e alterar qualquer senha temporária imediatamente.

## Descoberta, SEO e agentes de IA

As páginas públicas possuem títulos e descrições específicos, URLs canônicas, Open Graph, JSON-LD com entidades visíveis, trilhas de navegação e links internos entre o diretório, localidades e perfis de casas. A página institucional `public/sobre.php` apresenta o propósito da plataforma, enquanto `public/directory.php` concentra a descoberta de casas que autorizaram sua publicação.

O `public/sitemap.php` lista somente páginas canônicas, localidades com pelo menos uma casa pública e perfis ativos publicados. Ele inclui `lastmod` baseado na atualização real do cadastro central. O `public/robots.txt` mantém ações, autenticação e áreas privadas fora do rastreamento, e o `public/llms.txt` oferece uma descrição legível por agentes que adotem esse formato. Nenhuma dessas camadas transforma um perfil privado em público.

Páginas de busca por cidade só recebem sinal de indexação quando retornam casas públicas reais. Buscas por geolocalização do visitante e páginas sem resultados são marcadas como `noindex`; a posição do visitante continua sendo usada somente na consulta atual. A tag GA4 é carregada apenas após consentimento explícito e não é inserida nas áreas autenticadas ou nos endpoints de formulários.

SEO e descoberta por IA não garantem posicionamento, citações ou recomendações. Para melhorar a relevância de uma casa, a liderança deve manter atualizados os dados que escolheu divulgar, escrever uma apresentação objetiva e solicitar links de fontes externas confiáveis, como página institucional, rede social oficial ou diretório comunitário — sem publicar endereço ou informação ritual que não queira tornar pública. Consulte também as orientações do [Google Search Central](https://developers.google.com/search/docs/fundamentals/seo-starter-guide) e as [Diretrizes para Webmasters do Bing](https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a).

## Moderação global de centros

A administração global possui controles separados para evitar que uma ação reversível seja confundida com exclusão definitiva. **Ocultar do diretório** remove a casa da busca e do mapa, mas preserva o acesso e os dados. **Suspender centro** retira a casa do diretório e impede a operação normal até que ela seja reativada. **Reativar e publicar** restaura um centro suspenso no diretório.

A exclusão definitiva só pode ser feita pela administração global. O fluxo exige um motivo com pelo menos 10 caracteres e a digitação exata de `EXCLUIR <slug>`. Antes de remover o banco isolado, a aplicação cria um dump SQL compactado em diretório privado, registra o início da operação e mantém a casa em quarentena. A remoção central só é concluída depois que o banco isolado e o registro do tenant são removidos; falhas preservam o centro em estado inativo e registram a ocorrência na auditoria.

Backups de exclusão devem ter permissões restritas, retenção definida e proteção operacional compatível com a política da instalação. A exclusão definitiva não deve ser usada para uma simples retirada do diretório.

## Segurança e privacidade

O projeto lida potencialmente com CPF, endereço, telefone, datas pessoais, registros espirituais e informações financeiras. A LGPD classifica dados referentes à convicção religiosa como dados pessoais sensíveis, o que exige cuidados reforçados [5]. Por isso, uma instalação real deve aplicar HTTPS, controle de acesso por papel, backups criptografados, retenção mínima de dados, trilhas de auditoria e política de consentimento compatível com a legislação aplicável. A política pública nacional voltada a povos e comunidades tradicionais de terreiro reforça a importância da autonomia, da preservação de saberes e do enfrentamento à intolerância [6].

O diretório usa [OpenStreetMap](https://www.openstreetmap.org/) por meio de mapa no navegador, com atribuição visível. A instalação deve respeitar a política pública de uso de tiles e considerar um provedor apropriado ou infraestrutura própria conforme a escala de acesso [7]. Nunca armazene a localização da pessoa que pesquisa sem finalidade clara e consentimento específico.

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

As próximas etapas priorizam relatórios financeiros, backups verificáveis, permissões ainda mais granulares, participação em agenda, anexos em armazenamento privado, restauração de dados e testes automatizados. Cada melhoria deve preservar a separação entre terreiros, o uso em dispositivos móveis e a autonomia espiritual de cada casa.

## Licença

Este projeto é distribuído sob a licença MIT. Consulte [LICENSE](LICENSE).

## Referências

[1]: https://www.php.net/docs.php "Documentação oficial do PHP"
[2]: https://www.php.net/manual/en/book.pdo.php "Documentação oficial do PDO"
[3]: https://mariadb.com/kb/en/documentation/ "Documentação oficial do MariaDB"
[4]: https://www.php.net/manual/en/install.fpm.php "Documentação oficial do PHP-FPM"
[5]: https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm "Lei Geral de Proteção de Dados Pessoais"
[6]: https://www.gov.br/igualdaderacial/pt-br/assuntos/programas-e-projetos/politica-nacional-para-povos-e-comunidades-tradicionais-de-terreiro-e-matriz-africana "Política Nacional para Povos e Comunidades Tradicionais de Terreiro e de Matriz Africana"
[7]: https://operations.osmfoundation.org/policies/tiles/ "Política de uso de tiles do OpenStreetMap"
