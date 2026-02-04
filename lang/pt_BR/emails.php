<?php

return [
    'inbound' => [
        'processed' => 'E-mail recebido processado com sucesso.',
        'failed' => 'Falha ao processar e-mail recebido.',
        'ignored' => 'E-mail recebido ignorado.',
        'duplicate' => 'E-mail duplicado detectado, ignorando.',
        'no_sender' => 'Não foi possível determinar o endereço de e-mail do remetente.',
        'no_user' => 'Nenhum usuário encontrado para o endereço de e-mail :email.',
        'ticket_created' => 'Novo chamado criado a partir do e-mail.',
        'comment_added' => 'Comentário adicionado ao chamado a partir do e-mail.',
    ],

    'threading' => [
        'matched_by_header' => 'Vinculado ao chamado via cabeçalho de e-mail.',
        'matched_by_reference' => 'Vinculado ao chamado via referência no assunto.',
        'no_match' => 'Nenhum chamado existente encontrado, criando novo chamado.',
    ],

    'channels' => [
        'imap' => 'IMAP',
        'mailgun' => 'Mailgun',
        'sendgrid' => 'SendGrid',
    ],

    'errors' => [
        'driver_not_installed' => 'O driver :driver requer o pacote :package. Instale-o via: composer require :package',
        'connection_failed' => 'Falha ao conectar ao servidor de e-mail: :error',
        'invalid_signature' => 'Assinatura de webhook inválida.',
        'channel_not_found' => 'Canal de e-mail não encontrado.',
        'channel_inactive' => 'O canal de e-mail não está ativo.',
        'processing_error' => 'Erro ao processar e-mail: :error',
    ],
];
