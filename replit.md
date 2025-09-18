# Sistema de Gerenciamento de Construção Civil

## Visão Geral
Sistema web completo para gerenciamento de construção civil desenvolvido com PHP (backend) e JavaScript vanilla (frontend).

## Funcionalidades Implementadas
- **Dashboard**: Visão geral com estatísticas das obras
- **Gerenciamento de Obras**: CRUD completo (criar, editar, excluir, listar)
- **Materiais**: Associar materiais às obras com controle de quantidade e custo
- **Financeiro**: Lançamento de despesas/receitas e relatórios básicos
- **Equipe**: Cadastro de pessoas por obra com função e custo/hora

## Arquitetura
### Backend (PHP 8.2)
- API RESTful em PHP
- Banco PostgreSQL para persistência
- Estrutura modular com separação de responsabilidades
- Validação e sanitização de dados

### Frontend
- HTML5 semântico
- CSS3 com design responsivo e tema de construção
- JavaScript vanilla para interações dinâmicas
- Interface amigável com modais e alertas

### Banco de Dados
- PostgreSQL com 4 tabelas principais:
  - `projects`: Dados das obras
  - `materials`: Materiais por obra
  - `transactions`: Movimentações financeiras
  - `team_members`: Membros da equipe por obra

## Estado Atual
- ✅ Backend API completo
- ✅ Frontend implementado
- ✅ Sistema funcionando
- ✅ Design responsivo
- ✅ Logo personalizado gerado

## Próximos Passos Sugeridos
- Relatórios financeiros avançados
- Sistema de autenticação
- Upload de fotos das obras
- Controle de progresso/cronograma
- Notificações e alertas

## Tecnologias
- PHP 8.2
- PostgreSQL
- HTML5/CSS3/JavaScript
- FontAwesome (ícones)
- Design responsivo