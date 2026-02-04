<?php

return [
    'ticket' => 'Chamado',
    'tickets' => 'Chamados',
    'create' => 'Criar Chamado',
    'edit' => 'Editar Chamado',
    'delete' => 'Excluir Chamado',
    'close' => 'Fechar Chamado',
    'reopen' => 'Reabrir Chamado',
    'assign' => 'Atribuir Chamado',
    'unassign' => 'Desatribuir Chamado',

    'fields' => [
        'title' => 'Título',
        'description' => 'Descrição',
        'status' => 'Status',
        'priority' => 'Prioridade',
        'department' => 'Departamento',
        'category' => 'Categoria',
        'assigned_to' => 'Atribuído Para',
        'created_by' => 'Criado Por',
        'reference_number' => 'Número de Referência',
        'source' => 'Origem',
        'due_at' => 'Data de Vencimento',
        'closed_at' => 'Fechado Em',
        'created_at' => 'Criado Em',
        'updated_at' => 'Atualizado Em',
    ],

    'sources' => [
        'web' => 'Web',
        'email' => 'E-mail',
        'api' => 'API',
    ],

    'validation' => [
        'title_required' => 'O título do chamado é obrigatório.',
        'title_max' => 'O título do chamado não deve exceder :max caracteres.',
        'description_required' => 'A descrição do chamado é obrigatória.',
        'department_required' => 'Por favor, selecione um departamento.',
        'department_invalid' => 'O departamento selecionado é inválido.',
        'category_invalid' => 'A categoria selecionada é inválida.',
        'priority_invalid' => 'A prioridade selecionada é inválida.',
        'status_invalid' => 'O status selecionado é inválido.',
        'status_transition_invalid' => 'Não é possível transicionar de :from para :to.',
        'attachment_invalid_type' => 'O tipo de arquivo :type não é permitido.',
        'attachment_too_large' => 'O arquivo não deve exceder :max KB.',
        'attachment_limit_exceeded' => 'Você pode anexar no máximo :max arquivos por comentário.',
    ],

    'messages' => [
        'created' => 'Chamado criado com sucesso.',
        'updated' => 'Chamado atualizado com sucesso.',
        'deleted' => 'Chamado excluído com sucesso.',
        'closed' => 'Chamado fechado com sucesso.',
        'reopened' => 'Chamado reaberto com sucesso.',
        'assigned' => 'Chamado atribuído com sucesso.',
        'unassigned' => 'Chamado desatribuído com sucesso.',
        'not_found' => 'Chamado não encontrado.',
    ],
];
