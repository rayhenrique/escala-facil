<?php

return [
    'current' => env('APP_VERSION', '1.3.0'),

    'releases' => [
        [
            'version' => '1.3.0',
            'date' => '2026-09-26',
            'title' => 'Notificações e comprovantes de troca',
            'summary' => 'Cada etapa da troca agora avisa as pessoas envolvidas e mantém um comprovante detalhado da movimentação.',
            'highlights' => [
                'Substituto avisado ao receber uma nova solicitação de troca.',
                'Gerentes avisados quando uma troca aceita aguarda decisão.',
                'Solicitante e substituto avisados após aceite, aprovação ou rejeição.',
                'Nova Central de Notificações com contador e controle de leitura.',
                'Comprovante com referência, plantão, participantes e responsável pela decisão.',
                'Guia completo de deploy na Hostinger com CloudPanel, SSL, fila e atualizações.',
            ],
        ],
        [
            'version' => '1.2.0',
            'date' => '2026-09-26',
            'title' => 'Central de ajuda e novidades',
            'summary' => 'O sistema passa a explicar seus fluxos dentro da própria interface e avisar cada usuário sobre novas versões.',
            'highlights' => [
                'Novo módulo Ajuda com manual completo para gerentes e profissionais.',
                'Histórico de versões disponível dentro do sistema.',
                'Modal de novidades exibido uma vez por usuário em cada nova versão.',
                'README e processo de lançamento atualizados.',
                'Rodapé global com autoria da KL Tecnologia e versão do sistema.',
                'Licença proprietária e propriedade intelectual destacadas no repositório.',
            ],
        ],
        [
            'version' => '1.1.0',
            'date' => '2026-09-26',
            'title' => 'Calendário e gestão mais rápida',
            'summary' => 'A navegação e os cadastros foram reorganizados para acelerar a rotina da gerência.',
            'highlights' => [
                'Calendário mensal com plantões e profissionais por dia.',
                'Sidebar recolhível no desktop e gaveta preservada no celular.',
                'Formulários dos CRUDs abertos em modais responsivos.',
                'Massa de demonstração isolada, instalável e removível por comando.',
            ],
        ],
        [
            'version' => '1.0.0',
            'date' => '2026-09-26',
            'title' => 'MVP operacional',
            'summary' => 'Primeira versão completa para gestão diária de plantões.',
            'highlights' => [
                'CRUD completo de usuários, setores e categorias.',
                'Interface totalmente em português do Brasil e mobile-first.',
                'Sidebar com ícones para todos os módulos autenticados.',
                'Proteções de histórico e compatibilidade nos cadastros.',
            ],
        ],
        [
            'version' => '0.4.0',
            'date' => '2026-09-26',
            'title' => 'Painéis e qualidade',
            'summary' => 'A operação ganhou indicadores, alertas e cobertura automatizada por testes.',
            'highlights' => [
                'Dashboard gerencial com cobertura e pendências reais.',
                'Cartões e badges de status para leitura rápida.',
                'Testes das regras de limite e compatibilidade de trocas.',
            ],
        ],
        [
            'version' => '0.3.0',
            'date' => '2026-09-25',
            'title' => 'Fluxos de plantão',
            'summary' => 'Os principais processos da gerência e do profissional foram conectados.',
            'highlights' => [
                'Gestão de escalas e agenda individual.',
                'Solicitação, aceite e aprovação de trocas.',
                'Registro de ausência justificada com anexo privado.',
            ],
        ],
        [
            'version' => '0.2.0',
            'date' => '2026-09-25',
            'title' => 'Domínio e acesso',
            'summary' => 'Modelos, relacionamentos, autenticação e autorização passaram a proteger o domínio.',
            'highlights' => [
                'Relacionamentos e casts dos modelos Eloquent.',
                'Login, recuperação de senha e redirecionamento por perfil.',
                'Middleware e Policies para separar gerência e profissionais.',
            ],
        ],
        [
            'version' => '0.1.0',
            'date' => '2026-09-25',
            'title' => 'Fundação do Escala Fácil',
            'summary' => 'Estrutura inicial do projeto e banco de dados do MVP.',
            'highlights' => [
                'Laravel, Livewire, Tailwind CSS e Breeze configurados.',
                'Migrations de categorias, setores, usuários, plantões, trocas e ausências.',
                'Seeders iniciais para desenvolvimento local.',
            ],
        ],
    ],
];
