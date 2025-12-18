# Sistema de Gerenciamento Pacheco Empreendimentos

## Visão Geral
Sistema web completo de gerenciamento de construção desenvolvido com PHP (backend) e JavaScript vanilla (frontend) para a **Pacheco Empreendimentos**.

## Funcionalidades Implementadas

### Dashboard
- Visão geral com estatísticas das obras
- Resumo de orçamentos e gastos
- Contador de equipe e materiais
- Cards com obras recentes
- Notificações de prazo (obras com prazo vencendo)
- Status de obras concluídas
- Botão para ocultar valores

### Gerenciamento de Obras
- CRUD completo (criar, editar, excluir, listar)
- Upload de fotos das obras com descrição
- Controle de prazo, orçamento e responsável
- Status da obra (ativa, pausada, concluída)
- Anexo de documentos (PDF, DOC, XLS)
- Aba de detalhes com materiais, equipe, financeiro, fotos e documentos

### Materiais
- Associar materiais às obras específicas
- **NOVO: Integração com inventário** - Selecionar itens do estoque
- Dedução automática do estoque ao vincular material
- Código único de transação (MAT-YYYYMMDD-XXXXXX)
- Campo de descrição e foto
- Cálculo automático de custos totais

### Financeiro
- Lançamento de despesas e receitas por obra
- **NOVO: Código único de transação** (DES/REC-YYYYMMDD-XXXXXX)
- **NOVO: Anexar comprovantes** (fotos e documentos)
- Relatórios financeiros com gráficos
- Controle de orçamento vs gasto real
- Visualização de saldo restante

### Equipe
- Cadastro de membros
- **NOVO: Atribuição a múltiplas obras** com valores diferentes
- Tabela `project_team_assignments` para relação N:N
- Histórico de obras por membro
- Foto de perfil dos membros
- Função, tipo de pagamento e valor por obra

### Inventário da Empresa
- Controle centralizado de estoque geral
- Cadastro de materiais com foto
- Quantidade, unidade, custo unitário
- Alerta de estoque baixo (quantidade mínima)
- Valor total do inventário
- **NOVO: Código único de transação** (ENT/SAI-YYYYMMDD-XXXXXX)
- **NOVO: Exportação de PDF** real (download automático)
- Histórico de Movimentações:
  - Registro de entrada/saída de materiais
  - Rastreamento de envios para clientes/obras
  - Vinculação com obras específicas
  - Observações e notas de movimentação

### Sistema de Documentos
- **NOVO: Anexar documentos** a projetos
- Suporte para PDF, DOC, DOCX, XLS, XLSX, TXT
- Campo de descrição para cada documento
- Tabela dedicada `documents`

### Sistema de Histórico/Auditoria
- Registro automático de todas as alterações
- Histórico por registro específico
- Rastreamento de criação, edição e exclusão
- Visualização de valores antigos vs novos

### Upload de Imagens
- Upload de fotos em todas as seções
- **NOVO: Campo de descrição** para cada imagem
- Suporte para obras, materiais, transações e equipe
- Validação de tipo e tamanho de arquivo (10MB)
- Armazenamento seguro em pasta uploads/

## Arquitetura

### Backend (PHP 8.2)
- API RESTful modular
- PostgreSQL para persistência
- Upload de arquivos configurado
- Validação e sanitização de dados
- Sistema de auditoria automático
- Geração de códigos únicos para transações

### Frontend
- HTML5 semântico
- CSS3 responsivo com cores da Pacheco (laranja #F5A623 e azul #2E3B5B)
- JavaScript vanilla para interações dinâmicas
- Upload assíncrono de imagens e documentos
- Interface intuitiva com modais
- Biblioteca html2pdf.js para geração de PDF

### Banco de Dados PostgreSQL
**Tabelas principais:**
- `projects` - Dados das obras
- `materials` - Materiais por obra (com inventory_id e transaction_code)
- `transactions` - Movimentações financeiras (com transaction_code e receipt_path)
- `team_members` - Membros da equipe
- `project_team_assignments` - Atribuições de equipe a obras (N:N)
- `inventory` - Inventário geral da empresa
- `inventory_movements` - Histórico de movimentações do estoque
- `documents` - Documentos anexados a registros
- `images` - Imagens com descrições
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
- ✅ Sistema de upload de imagens com descrição
- ✅ Inventário com código único e PDF
- ✅ Materiais integrados com inventário
- ✅ Transações com comprovantes
- ✅ Equipe com múltiplas obras
- ✅ Sistema de documentos
- ✅ Sistema de auditoria implementado
- ✅ Design responsivo com logo Pacheco

## Tecnologias
- PHP 8.2
- PostgreSQL
- HTML5/CSS3/JavaScript
- FontAwesome (ícones)
- html2pdf.js (geração de PDF)
- Design responsivo mobile-first
