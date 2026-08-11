# Como contribuir

Obrigado por considerar uma contribuição ao Meu Terreiro. O projeto existe para apoiar a organização de casas de axé com respeito à autonomia de cada terreiro, à privacidade das pessoas e à diversidade das tradições afro-brasileiras.

## Antes de começar

Leia o `README.md`, o `SECURITY.md` e o `CODE_OF_CONDUCT.md`. Não publique dados reais de terreiros, nomes de pessoas, CPF, endereços, telefones, pontos riscados, recados espirituais, dumps de banco ou qualquer outro conteúdo que não tenha autorização explícita.

## Fluxo recomendado

1. Abra uma issue para explicar o problema ou a proposta.
2. Crie uma branch curta a partir de `main`, como `feat/agenda-giras` ou `fix/login-sessao`.
3. Faça mudanças pequenas, revisáveis e acompanhadas de documentação quando necessário.
4. Execute a validação de sintaxe PHP nos arquivos alterados e revise o diff.
5. Faça um commit com mensagem objetiva e explicativa.
6. Envie a branch para o GitHub e abra um pull request.

Exemplo:

```bash
git checkout main
git pull --ff-only origin main
git checkout -b feat/agenda-giras

# implemente e valide a alteração
find . -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check

git add .
git commit -m "feat: adiciona agenda de giras"
git push -u origin feat/agenda-giras
```

Toda alteração deve ter um commit que explique claramente o que foi feito e deve ser enviada ao GitHub. Não faça push de arquivos locais de configuração, logs, artefatos de build ou credenciais.

## Convenção de commits

Use, sempre que possível, os prefixos `feat:`, `fix:`, `docs:`, `security:`, `refactor:` e `chore:`. Um bom commit descreve a intenção da mudança, por exemplo: `security: impede acesso direto a dados do tenant`.

## Pull requests

A descrição do pull request deve explicar o contexto, a solução, os testes executados, as alterações de banco e qualquer impacto em Nginx, PHP-FPM ou permissões de arquivos. Mudanças no Nginx devem incluir o escopo exato do virtual host e a confirmação de que `nginx -t` foi executado.

Pull requests que tratem de estética devem explicar como a mudança melhora legibilidade, contraste, acessibilidade ou coerência visual. Referências a Umbanda, Candomblé e outras tradições devem ser tratadas com pesquisa, respeito e autorização quando envolverem imagens, cantos, pontos ou elementos litúrgicos.

## Dados de teste

Use dados fictícios e claramente identificados como teste. Não copie dados de produção para ambientes de desenvolvimento. Para reproduzir um problema, descreva a estrutura mínima necessária sem incluir conteúdo sensível.
