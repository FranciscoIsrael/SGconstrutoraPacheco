# Sistema de Gerenciamento Pacheco Empreendimentos

## Visão Geral
Sistema web completo de gerenciamento de construção desenvolvido com PHP (backend) e JavaScript vanilla (frontend) para a **Pacheco Empreendimentos**.

## Funcionalidades Implementadas

### Dashboard
- Visão geral com estatísticas das obras
- Resumo de orçamentos e gastos
- Contador de equipe e materiais
- Cards com obras recentes

### Gerenciamento de Obras
- CRUD completo (criar, editar, excluir, listar)
- Upload de fotos das obras
- Controle de prazo, orçamento e responsável
- Status da obra (ativa, pausada, concluída)

### Materiais
- Associar materiais às obras específicas
- Cadastro com foto, quantidade, unidade e custo
- Cálculo automático de custos totais
- Controle de materiais por obra

### Financeiro
- Lançamento de despesas e receitas por obra
- Upload de comprovantes (fotos/documentos)
- Relatórios financeiros com gráficos
- Controle de orçamento vs gasto real
- Visualização de saldo restante

### Equipe
- Cadastro de membros por obra
- Foto de perfil dos membros
- Função e custo por hora
- Gestão completa da equipe

### **NOVO: Inventário da Empresa**
- Controle centralizado de estoque geral
- Cadastro de materiais com foto
- Quantidade, unidade, custo unitário
- Alerta de estoque baixo (quantidade mínima)
- Valor total do inventário
- **Histórico de Movimentações**:
  - Registro de entrada/saída de materiais
  - Rastreamento de envios para clientes/obras
  - Vinculação com obras específicas
  - Observações e notas de movimentação
  
### **NOVO: Sistema de Histórico/Auditoria**
- Registro automático de todas as alterações
- Histórico por registro específico
- Rastreamento de criação, edição e exclusão
- Visualização de valores antigos vs novos

### **NOVO: Upload de Imagens**
- Upload de fotos em todas as seções
- Suporte para obras, materiais, transações e equipe
- Validação de tipo e tamanho de arquivo
- Armazenamento seguro em pasta uploads/

## Arquitetura

### Backend (PHP 8.2)
- API RESTful modular
- PostgreSQL para persistência
- Upload de arquivos configurado
- Validação e sanitização de dados
- Sistema de auditoria automático

### Frontend
- HTML5 semântico
- CSS3 responsivo com cores da Pacheco (laranja #F5A623 e azul #2E3B5B)
- JavaScript vanilla para interações dinâmicas
- Upload assíncrono de imagens
- Interface intuitiva com modais

### Banco de Dados PostgreSQL
**Tabelas principais:**
- `projects` - Dados das obras (com campo image_path)
- `materials` - Materiais por obra (com campo image_path)
- `transactions` - Movimentações financeiras (com campo image_path)
- `team_members` - Membros da equipe (com campo image_path)
- `inventory` - Inventário geral da empresa
- `inventory_movements` - Histórico de movimentações do estoque
- `audit_history` - Registro de auditoria de mudanças

## Branding
- Logo: Pacheco Empreendimentos
- Cores: 
  - Primária: Laranja #F5A623
  - Secundária: Azul #2E3B5B
- Design responsivo e profissional

## Estado Atual
- ✅ Backend API completo com inventário
- ✅ Frontend totalmente implementado
- ✅ Sistema de upload de imagens funcionando
- ✅ Inventário da empresa com histórico
- ✅ Sistema de auditoria implementado
- ✅ Design responsivo com logo Pacheco
- ✅ Todas as funcionalidades testadas

## Próximos Passos Sugeridos
- Relatórios financeiros avançados com gráficos
- Sistema de autenticação e permissões
- Notificações de estoque baixo
- Cronograma de obras com timeline
- Dashboard com gráficos interativos
- Exportação de relatórios em PDF

## Tecnologias
- PHP 8.2
- PostgreSQL
- HTML5/CSS3/JavaScript
- FontAwesome (ícones)
- Design responsivo mobile-first