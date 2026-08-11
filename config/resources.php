<?php
/** @return array<string, array<string, mixed>> */
function meu_terreiro_resources(): array
{
    $filhoSource = ['table' => 'filhos', 'value' => 'id', 'label' => 'nome', 'order' => 'nome'];
    $obrigacaoSource = ['table' => 'obrigacoes_tipo', 'value' => 'id', 'label' => 'nome', 'order' => 'nome'];
    $itemSource = ['table' => 'itens_estoque', 'value' => 'id', 'label' => 'nome', 'order' => 'nome'];
    $fornecedorSource = ['table' => 'fornecedores', 'value' => 'id', 'label' => 'nome', 'order' => 'nome'];

    return [
        'filhos' => [
            'title' => 'Filhos de santo', 'icon' => 'fa-people-group', 'description' => 'Cadastros, funções e situação de participação na casa.', 'table' => 'filhos', 'access' => 'manage',
            'fields' => [
                ['name'=>'nome','label'=>'Nome completo','type'=>'text','required'=>true],
                ['name'=>'cpf','label'=>'CPF (opcional)','type'=>'text'],
                ['name'=>'data_nascimento','label'=>'Data de nascimento','type'=>'date'],
                ['name'=>'whatsapp','label'=>'WhatsApp','type'=>'text'],
                ['name'=>'endereco','label'=>'Endereço','type'=>'textarea'],
                ['name'=>'data_entrada','label'=>'Data de entrada na casa','type'=>'date'],
                ['name'=>'cargo','label'=>'Cargo ou função','type'=>'text','placeholder'=>'Ex.: Filho de santo, Ogã, Ekedi'],
                ['name'=>'status','label'=>'Situação','type'=>'select','required'=>true,'options'=>['Ativo'=>'Ativo','Afastado'=>'Afastado','Inativo'=>'Inativo']],
            ],
            'list' => ['nome','cargo','status','data_entrada'],
        ],
        'agenda' => [
            'title' => 'Agenda de giras e atividades', 'icon' => 'fa-calendar-days', 'description' => 'Planeje giras, obrigações, estudos, festas, reuniões e tarefas coletivas.', 'table' => 'agenda', 'access' => 'manage',
            'fields' => [
                ['name'=>'titulo','label'=>'Título','type'=>'text','required'=>true],
                ['name'=>'tipo','label'=>'Tipo','type'=>'select','required'=>true,'options'=>['Gira'=>'Gira','Obrigação'=>'Obrigação','Festa'=>'Festa','Reunião'=>'Reunião','Estudo'=>'Estudo','Ação social'=>'Ação social','Manutenção'=>'Manutenção']],
                ['name'=>'data_hora','label'=>'Data e horário','type'=>'datetime-local','required'=>true],
                ['name'=>'local_evento','label'=>'Local','type'=>'text'],
                ['name'=>'capacidade','label'=>'Limite de participantes (opcional)','type'=>'number'],
                ['name'=>'status','label'=>'Situação','type'=>'select','required'=>true,'options'=>['Planejado'=>'Planejado','Confirmado'=>'Confirmado','Concluído'=>'Concluído','Cancelado'=>'Cancelado']],
                ['name'=>'visibilidade','label'=>'Visibilidade','type'=>'select','required'=>true,'options'=>['Interno'=>'Interno','Restrito'=>'Restrito','Dirigência'=>'Somente dirigência']],
                ['name'=>'descricao','label'=>'Orientações e observações','type'=>'textarea'],
            ],
            'list' => ['titulo','tipo','data_hora','status'],
        ],
        'obrigacoes_tipo' => [
            'title' => 'Tipos de obrigações', 'icon' => 'fa-scroll', 'description' => 'Defina apenas os nomes e orientações administrativas autorizadas pela casa.', 'table' => 'obrigacoes_tipo', 'access' => 'manage',
            'fields' => [
                ['name'=>'nome','label'=>'Nome da obrigação','type'=>'text','required'=>true],
                ['name'=>'nivel_sigilo','label'=>'Sigilo','type'=>'select','required'=>true,'options'=>['Interno'=>'Interno','Restrito'=>'Restrito','Dirigência'=>'Somente dirigência']],
                ['name'=>'descricao','label'=>'Observações administrativas','type'=>'textarea'],
            ],
            'list' => ['nome','nivel_sigilo','descricao'],
        ],
        'registros_obrigacoes' => [
            'title' => 'Histórico de obrigações', 'icon' => 'fa-certificate', 'description' => 'Registre datas e observações autorizadas; o conteúdo é restrito.', 'table' => 'filhos_obrigacoes', 'access' => 'manage',
            'fields' => [
                ['name'=>'id_filho','label'=>'Filho de santo','type'=>'select_db','source'=>$filhoSource,'required'=>true],
                ['name'=>'id_obrigacao','label'=>'Obrigação','type'=>'select_db','source'=>$obrigacaoSource,'required'=>true],
                ['name'=>'data_realizacao','label'=>'Data de realização','type'=>'date','required'=>true],
                ['name'=>'observacoes','label'=>'Observações restritas','type'=>'textarea'],
            ],
            'list' => ['id_filho','id_obrigacao','data_realizacao','observacoes'],
        ],
        'entidades' => [
            'title' => 'Entidades e registros reservados', 'icon' => 'fa-star-and-crescent', 'description' => 'Cadastre características e referências apenas quando autorizado. Pontos e recados são restritos por padrão.', 'table' => 'entidades', 'access' => 'manage',
            'fields' => [
                ['name'=>'id_filho','label'=>'Filho de santo','type'=>'select_db','source'=>$filhoSource,'required'=>true],
                ['name'=>'nome','label'=>'Nome de referência','type'=>'text','required'=>true],
                ['name'=>'tipo','label'=>'Linha ou tipo','type'=>'text','placeholder'=>'Conforme a tradição da casa'],
                ['name'=>'cor_vela','label'=>'Cor de vela','type'=>'text'],
                ['name'=>'ponto_riscado_url','label'=>'Link privado do ponto riscado (opcional)','type'=>'url'],
                ['name'=>'recados','label'=>'Recados autorizados','type'=>'textarea'],
                ['name'=>'nivel_sigilo','label'=>'Sigilo','type'=>'select','required'=>true,'options'=>['Restrito'=>'Restrito','Dirigência'=>'Somente dirigência']],
            ],
            'list' => ['id_filho','nome','tipo','nivel_sigilo'],
        ],
        'mensalidades' => [
            'title' => 'Mensalidades', 'icon' => 'fa-receipt', 'description' => 'Registre contribuições com transparência e respeito à realidade de cada pessoa.', 'table' => 'mensalidades', 'access' => 'finance',
            'fields' => [
                ['name'=>'id_filho','label'=>'Filho de santo','type'=>'select_db','source'=>$filhoSource,'required'=>true],
                ['name'=>'referencia_mes_ano','label'=>'Referência','type'=>'month','required'=>true],
                ['name'=>'valor_pago','label'=>'Valor pago','type'=>'decimal','required'=>true],
                ['name'=>'data_pagamento','label'=>'Data do pagamento','type'=>'date','required'=>true],
                ['name'=>'meio_pagamento','label'=>'Meio de pagamento','type'=>'select','options'=>['Dinheiro'=>'Dinheiro','PIX'=>'PIX','Transferência'=>'Transferência','Cartão'=>'Cartão','Outro'=>'Outro']],
                ['name'=>'registrado_por','label'=>'Registrado por','type'=>'text'],
            ],
            'list' => ['id_filho','referencia_mes_ano','valor_pago','data_pagamento'],
        ],
        'financeiro' => [
            'title' => 'Lançamentos financeiros', 'icon' => 'fa-coins', 'description' => 'Entradas e saídas para apoiar transparência, prestação de contas e planejamento.', 'table' => 'lancamentos_financeiros', 'access' => 'finance',
            'fields' => [
                ['name'=>'tipo','label'=>'Movimentação','type'=>'select','required'=>true,'options'=>['Entrada'=>'Entrada','Saída'=>'Saída']],
                ['name'=>'categoria','label'=>'Categoria','type'=>'text','required'=>true,'placeholder'=>'Ex.: Doação, manutenção, alimentos'],
                ['name'=>'descricao','label'=>'Descrição','type'=>'text','required'=>true],
                ['name'=>'valor','label'=>'Valor','type'=>'decimal','required'=>true],
                ['name'=>'data_lancamento','label'=>'Data','type'=>'date','required'=>true],
                ['name'=>'meio_pagamento','label'=>'Meio de pagamento','type'=>'select','options'=>['Dinheiro'=>'Dinheiro','PIX'=>'PIX','Transferência'=>'Transferência','Cartão'=>'Cartão','Outro'=>'Outro']],
                ['name'=>'comprovante_url','label'=>'Link do comprovante (opcional)','type'=>'url'],
                ['name'=>'visibilidade','label'=>'Visibilidade','type'=>'select','required'=>true,'options'=>['Financeiro'=>'Equipe financeira','Dirigência'=>'Somente dirigência']],
            ],
            'list' => ['tipo','categoria','descricao','valor','data_lancamento'],
        ],
        'estoque' => [
            'title' => 'Estoque e materiais', 'icon' => 'fa-boxes-stacked', 'description' => 'Acompanhe folhas, velas, alimentos, limpeza, cozinha e materiais de uso da casa.', 'table' => 'itens_estoque', 'access' => 'stock',
            'fields' => [
                ['name'=>'nome','label'=>'Item','type'=>'text','required'=>true],
                ['name'=>'categoria','label'=>'Categoria','type'=>'select','required'=>true,'options'=>['Folhas e ervas'=>'Folhas e ervas','Velas'=>'Velas','Bebidas'=>'Bebidas','Alimentos'=>'Alimentos','Limpeza'=>'Limpeza','Cozinha'=>'Cozinha','Papelaria'=>'Papelaria','Vestuário'=>'Vestuário','Manutenção'=>'Manutenção','Outro'=>'Outro']],
                ['name'=>'unidade','label'=>'Unidade','type'=>'text','required'=>true,'placeholder'=>'unidade, kg, maço, litro'],
                ['name'=>'quantidade_atual','label'=>'Quantidade atual','type'=>'decimal','required'=>true],
                ['name'=>'estoque_minimo','label'=>'Estoque mínimo','type'=>'decimal','required'=>true],
                ['name'=>'validade','label'=>'Validade (se aplicável)','type'=>'date'],
                ['name'=>'local_armazenamento','label'=>'Local de armazenamento','type'=>'text'],
                ['name'=>'observacoes','label'=>'Observações','type'=>'textarea'],
            ],
            'list' => ['nome','categoria','quantidade_atual','estoque_minimo','validade'],
        ],
        'movimentacoes_estoque' => [
            'title' => 'Movimentações de estoque', 'icon' => 'fa-right-left', 'description' => 'Registre entradas, saídas, perdas e ajustes para manter o inventário confiável.', 'table' => 'movimentacoes_estoque', 'access' => 'stock',
            'fields' => [
                ['name'=>'id_item','label'=>'Item','type'=>'select_db','source'=>$itemSource,'required'=>true],
                ['name'=>'tipo','label'=>'Tipo','type'=>'select','required'=>true,'options'=>['Entrada'=>'Entrada','Saída'=>'Saída','Ajuste'=>'Ajuste','Perda'=>'Perda']],
                ['name'=>'quantidade','label'=>'Quantidade','type'=>'decimal','required'=>true],
                ['name'=>'motivo','label'=>'Motivo','type'=>'text','required'=>true],
            ],
            'list' => ['id_item','tipo','quantidade','motivo','data_movimentacao'],
        ],
        'fornecedores' => [
            'title' => 'Fornecedores', 'icon' => 'fa-truck', 'description' => 'Mantenha contatos e referências de compras organizados.', 'table' => 'fornecedores', 'access' => 'stock',
            'fields' => [
                ['name'=>'nome','label'=>'Nome','type'=>'text','required'=>true],
                ['name'=>'categoria','label'=>'Categoria','type'=>'text'],
                ['name'=>'contato','label'=>'Pessoa ou canal de contato','type'=>'text'],
                ['name'=>'telefone','label'=>'Telefone','type'=>'text'],
                ['name'=>'observacoes','label'=>'Observações','type'=>'textarea'],
                ['name'=>'ativo','label'=>'Ativo','type'=>'checkbox'],
            ],
            'list' => ['nome','categoria','contato','telefone','ativo'],
        ],
        'compras' => [
            'title' => 'Compras e necessidades', 'icon' => 'fa-cart-shopping', 'description' => 'Organize solicitações, prioridades, aprovações e aquisições.', 'table' => 'compras', 'access' => 'stock',
            'fields' => [
                ['name'=>'descricao','label'=>'Item ou necessidade','type'=>'text','required'=>true],
                ['name'=>'id_fornecedor','label'=>'Fornecedor (opcional)','type'=>'select_db','source'=>$fornecedorSource],
                ['name'=>'valor_estimado','label'=>'Valor estimado','type'=>'decimal'],
                ['name'=>'data_necessidade','label'=>'Necessário até','type'=>'date'],
                ['name'=>'prioridade','label'=>'Prioridade','type'=>'select','required'=>true,'options'=>['Baixa'=>'Baixa','Normal'=>'Normal','Alta'=>'Alta','Urgente'=>'Urgente']],
                ['name'=>'status','label'=>'Situação','type'=>'select','required'=>true,'options'=>['Solicitada'=>'Solicitada','Aprovada'=>'Aprovada','Comprada'=>'Comprada','Cancelada'=>'Cancelada']],
                ['name'=>'observacoes','label'=>'Observações','type'=>'textarea'],
            ],
            'list' => ['descricao','valor_estimado','data_necessidade','prioridade','status'],
        ],
        'preparos' => [
            'title' => 'Cozinha e preparos', 'icon' => 'fa-utensils', 'description' => 'Organize preparos, responsáveis e destino, sem registrar detalhes que a casa considere sigilosos.', 'table' => 'preparos_alimentares', 'access' => 'manage',
            'fields' => [
                ['name'=>'nome','label'=>'Nome do preparo','type'=>'text','required'=>true],
                ['name'=>'data_preparo','label'=>'Data','type'=>'date','required'=>true],
                ['name'=>'responsavel','label'=>'Responsável','type'=>'text'],
                ['name'=>'destino','label'=>'Destino','type'=>'text'],
                ['name'=>'visibilidade','label'=>'Visibilidade','type'=>'select','required'=>true,'options'=>['Interno'=>'Interno','Restrito'=>'Restrito']],
                ['name'=>'observacoes','label'=>'Observações','type'=>'textarea'],
            ],
            'list' => ['nome','data_preparo','responsavel','destino','visibilidade'],
        ],
        'oferendas' => [
            'title' => 'Registros de oferendas', 'icon' => 'fa-bowl-food', 'description' => 'Registro reservado, com orientação ambiental e visibilidade limitada por padrão.', 'table' => 'oferendas_registros', 'access' => 'manage',
            'fields' => [
                ['name'=>'titulo','label'=>'Título de referência','type'=>'text','required'=>true],
                ['name'=>'data_registro','label'=>'Data','type'=>'date','required'=>true],
                ['name'=>'responsavel','label'=>'Responsável','type'=>'text'],
                ['name'=>'local_descricao','label'=>'Descrição geral do local','type'=>'text'],
                ['name'=>'orientacao_ambiental','label'=>'Cuidados ambientais e destino de materiais','type'=>'textarea'],
                ['name'=>'nivel_sigilo','label'=>'Sigilo','type'=>'select','required'=>true,'options'=>['Restrito'=>'Restrito','Dirigência'=>'Somente dirigência']],
                ['name'=>'observacoes','label'=>'Observações reservadas','type'=>'textarea'],
            ],
            'list' => ['titulo','data_registro','responsavel','nivel_sigilo'],
        ],
        'tarefas' => [
            'title' => 'Tarefas da casa', 'icon' => 'fa-list-check', 'description' => 'Distribua rotinas de limpeza, manutenção, cozinha, organização e segurança.', 'table' => 'tarefas_casa', 'access' => 'manage',
            'fields' => [
                ['name'=>'titulo','label'=>'Tarefa','type'=>'text','required'=>true],
                ['name'=>'categoria','label'=>'Categoria','type'=>'select','required'=>true,'options'=>['Limpeza'=>'Limpeza','Manutenção'=>'Manutenção','Cozinha'=>'Cozinha','Organização'=>'Organização','Segurança'=>'Segurança','Outro'=>'Outro']],
                ['name'=>'responsavel','label'=>'Responsável','type'=>'text'],
                ['name'=>'data_limite','label'=>'Prazo','type'=>'date'],
                ['name'=>'status','label'=>'Situação','type'=>'select','required'=>true,'options'=>['Pendente'=>'Pendente','Em andamento'=>'Em andamento','Concluída'=>'Concluída','Cancelada'=>'Cancelada']],
                ['name'=>'observacoes','label'=>'Observações','type'=>'textarea'],
            ],
            'list' => ['titulo','categoria','responsavel','data_limite','status'],
        ],
        'patrimonio' => [
            'title' => 'Patrimônio', 'icon' => 'fa-warehouse', 'description' => 'Inventarie bens, equipamentos, mobiliário e necessidades de manutenção.', 'table' => 'patrimonio', 'access' => 'manage',
            'fields' => [
                ['name'=>'nome','label'=>'Bem ou equipamento','type'=>'text','required'=>true],
                ['name'=>'categoria','label'=>'Categoria','type'=>'text'],
                ['name'=>'numero_identificacao','label'=>'Identificação','type'=>'text'],
                ['name'=>'valor_estimado','label'=>'Valor estimado','type'=>'decimal'],
                ['name'=>'estado_conservacao','label'=>'Estado de conservação','type'=>'select','required'=>true,'options'=>['Bom'=>'Bom','Atenção'=>'Atenção','Manutenção necessária'=>'Manutenção necessária']],
                ['name'=>'local_armazenamento','label'=>'Local','type'=>'text'],
                ['name'=>'observacoes','label'=>'Observações','type'=>'textarea'],
            ],
            'list' => ['nome','categoria','estado_conservacao','local_armazenamento'],
        ],
        'biblioteca' => [
            'title' => 'Biblioteca e estudos', 'icon' => 'fa-book-open', 'description' => 'Organize livros, materiais de estudo e links privados autorizados pela casa.', 'table' => 'biblioteca', 'access' => 'manage',
            'fields' => [
                ['name'=>'titulo','label'=>'Título','type'=>'text','required'=>true],
                ['name'=>'link_drive','label'=>'Link do material','type'=>'url','required'=>true],
                ['name'=>'categoria','label'=>'Categoria','type'=>'text'],
                ['name'=>'visibilidade','label'=>'Visibilidade','type'=>'select','required'=>true,'options'=>['Interno'=>'Interno','Restrito'=>'Restrito','Dirigência'=>'Somente dirigência']],
            ],
            'list' => ['titulo','categoria','visibilidade','link_drive'],
        ],
        'album' => [
            'title' => 'Álbum de memória', 'icon' => 'fa-images', 'description' => 'Registre links de fotos somente com autorização de imagem e com visibilidade controlada.', 'table' => 'album_fotos', 'access' => 'manage',
            'fields' => [
                ['name'=>'titulo','label'=>'Título','type'=>'text','required'=>true],
                ['name'=>'url_arquivo','label'=>'Link do arquivo','type'=>'url','required'=>true],
                ['name'=>'data_registro','label'=>'Data','type'=>'date'],
                ['name'=>'pessoas_identificadas','label'=>'Pessoas identificadas','type'=>'textarea'],
                ['name'=>'consentimento_confirmado','label'=>'Consentimento de imagem confirmado','type'=>'checkbox'],
                ['name'=>'visibilidade','label'=>'Visibilidade','type'=>'select','required'=>true,'options'=>['Interno'=>'Interno','Restrito'=>'Restrito','Dirigência'=>'Somente dirigência']],
                ['name'=>'descricao','label'=>'Descrição','type'=>'textarea'],
            ],
            'list' => ['titulo','data_registro','consentimento_confirmado','visibilidade'],
        ],
        'locais' => [
            'title' => 'Locais e referências de cuidado', 'icon' => 'fa-map-location-dot', 'description' => 'Folhas, ervas, encruzilhadas e outros locais são privados por padrão. Compartilhe somente o que a casa autorizar.', 'table' => 'locais_referencia', 'access' => 'manage',
            'fields' => [
                ['name'=>'nome','label'=>'Nome de referência','type'=>'text','required'=>true],
                ['name'=>'tipo','label'=>'Tipo','type'=>'select','required'=>true,'options'=>['Folha e erva'=>'Folha e erva','Encruzilhada'=>'Encruzilhada','Fonte'=>'Fonte','Mata'=>'Mata','Praia'=>'Praia','Mercado'=>'Mercado','Fornecedor'=>'Fornecedor','Outro'=>'Outro']],
                ['name'=>'localizacao_descricao','label'=>'Descrição privada do local','type'=>'textarea'],
                ['name'=>'latitude','label'=>'Latitude (opcional)','type'=>'decimal'],
                ['name'=>'longitude','label'=>'Longitude (opcional)','type'=>'decimal'],
                ['name'=>'condicao','label'=>'Condição de acesso','type'=>'select','required'=>true,'options'=>['Confirmar'=>'Confirmar antes de ir','Aberta'=>'Aberta','Fechada'=>'Fechada','Evitar'=>'Evitar','Sazonal'=>'Sazonal']],
                ['name'=>'acesso_restrito','label'=>'Acesso restrito à equipe autorizada','type'=>'checkbox'],
                ['name'=>'observacoes','label'=>'Observações de cuidado','type'=>'textarea'],
            ],
            'list' => ['nome','tipo','condicao','acesso_restrito'],
        ],
        'comunicados' => [
            'title' => 'Comunicados internos', 'icon' => 'fa-bullhorn', 'description' => 'Compartilhe avisos administrativos dentro da casa, sem exposição pública.', 'table' => 'comunicados', 'access' => 'manage',
            'fields' => [
                ['name'=>'titulo','label'=>'Título','type'=>'text','required'=>true],
                ['name'=>'mensagem','label'=>'Mensagem','type'=>'textarea','required'=>true],
                ['name'=>'publico','label'=>'Público','type'=>'select','required'=>true,'options'=>['Todos'=>'Todos','Equipe'=>'Equipe','Dirigência'=>'Somente dirigência']],
                ['name'=>'expira_em','label'=>'Expira em (opcional)','type'=>'datetime-local'],
            ],
            'list' => ['titulo','publico','publicado_em','expira_em'],
        ],
        'incidentes' => [
            'title' => 'Segurança e ocorrências', 'icon' => 'fa-shield-heart', 'description' => 'Registro sigiloso de ocorrências, providências e contatos de apoio. Acesso reservado à dirigência.', 'table' => 'incidentes_seguranca', 'access' => 'manage',
            'fields' => [
                ['name'=>'data_incidente','label'=>'Data','type'=>'date','required'=>true],
                ['name'=>'categoria','label'=>'Categoria','type'=>'select','required'=>true,'options'=>['Intolerância religiosa'=>'Intolerância religiosa','Ameaça'=>'Ameaça','Vandalismo'=>'Vandalismo','Conflito'=>'Conflito','Acidente'=>'Acidente','Outro'=>'Outro']],
                ['name'=>'descricao','label'=>'Descrição reservada','type'=>'textarea','required'=>true],
                ['name'=>'providencias','label'=>'Providências tomadas','type'=>'textarea'],
                ['name'=>'contato_apoio','label'=>'Contato de apoio','type'=>'text'],
                ['name'=>'status','label'=>'Situação','type'=>'select','required'=>true,'options'=>['Aberto'=>'Aberto','Em acompanhamento'=>'Em acompanhamento','Encerrado'=>'Encerrado']],
            ],
            'list' => ['data_incidente','categoria','status','descricao'],
        ],
    ];
}
