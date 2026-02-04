<?php

return [
    'ticket_created' => [
        'subject' => 'Novo Chamado Criado: :title',
        'greeting' => 'Olá!',
        'body' => 'Um novo chamado de suporte foi criado.',
        'reference' => 'Referência: :reference',
        'department' => 'Departamento: :department',
        'priority' => 'Prioridade: :priority',
        'action' => 'Ver Chamado',
    ],

    'ticket_status_changed' => [
        'subject' => 'Status do Chamado :reference Atualizado',
        'greeting' => 'Olá!',
        'body' => 'O status do seu chamado foi atualizado.',
        'from' => 'Status Anterior: :from',
        'to' => 'Novo Status: :to',
        'action' => 'Ver Chamado',
    ],

    'ticket_assigned' => [
        'subject' => 'Chamado :reference Atribuído a Você',
        'greeting' => 'Olá!',
        'body' => 'Um chamado de suporte foi atribuído a você.',
        'title' => 'Título: :title',
        'priority' => 'Prioridade: :priority',
        'action' => 'Ver Chamado',
    ],

    'comment_added' => [
        'subject' => 'Novo Comentário no Chamado :reference',
        'greeting' => 'Olá!',
        'body' => 'Um novo comentário foi adicionado ao seu chamado.',
        'author' => 'Comentário por: :author',
        'action' => 'Ver Chamado',
    ],

    'ticket_closed' => [
        'subject' => 'Chamado :reference Fechado',
        'greeting' => 'Olá!',
        'body' => 'Seu chamado de suporte foi fechado.',
        'title' => 'Título: :title',
        'action' => 'Ver Chamado',
    ],
];
