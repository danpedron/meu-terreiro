# Política de segurança

A plataforma pode lidar com dados pessoais, informações financeiras e registros relacionados à vida religiosa. Segurança e privacidade são requisitos do projeto, não itens opcionais.

## Como reportar uma vulnerabilidade

Não abra uma issue pública para relatar uma vulnerabilidade que possa expor dados ou permitir acesso indevido. Envie um relatório privado aos mantenedores pelo recurso de contato privado disponível no GitHub deste repositório, descrevendo a versão afetada, o impacto, os passos mínimos para reprodução e uma sugestão de correção, se houver.

Não inclua senhas, tokens, URLs privadas, nomes reais, documentos pessoais ou dumps de banco no relatório. Use valores fictícios e remova dados identificáveis.

## Regras obrigatórias

Nunca faça commit de senhas, chaves privadas, tokens, arquivos `.env`, `config/db_config.local.php`, cookies, logs de produção ou backups de banco. A configuração deve ser fornecida por variáveis de ambiente ou por um arquivo local ignorado pelo Git.

Se uma credencial aparecer em um commit, considere-a comprometida mesmo que o arquivo seja corrigido depois. Revogue a credencial, gere outra, verifique o histórico do repositório e informe os mantenedores.

O ambiente de produção deve usar HTTPS, sessões protegidas, senhas com `password_hash`, consultas preparadas, autorização por papel, backups criptografados, menor privilégio no MariaDB, monitoramento de logs e atualizações regulares do sistema operacional e do PHP.

## Privacidade por tenant

Um usuário deve conseguir consultar apenas os dados do terreiro ao qual está associado. Toda consulta deve obter a conexão do tenant por meio do catálogo central e validar o estado do tenant. Não aceite o nome do banco de dados diretamente de parâmetros HTTP.

Novas funcionalidades que exportem dados devem exigir autorização explícita, registrar a operação e excluir informações desnecessárias. Pontos riscados, recados, fotografias e registros rituais devem ser tratados como conteúdo sensível e jamais usados em exemplos públicos sem autorização.
