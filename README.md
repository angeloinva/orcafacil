# OrcaFacil - Gerenciador de Orçamentos de Obras

Projeto web mobile-first em **PHP + MySQL + CSS** para conectar:

- **Usuário final (cliente)**: cria orçamento com itens livres e acompanha respostas em interface simples.
- **Loja**: recebe todos os orçamentos (ou apenas de favoritos do cliente), responde com proposta comercial e acompanha fila de pedidos.

## Funcionalidades implementadas

- Cadastro e login com dois perfis (`client` e `store`)
- Dashboard do cliente em estilo conversa/listagem (fácil para celular)
- Criação de orçamento com:
  - título
  - observações
  - múltiplos itens (nome, quantidade e unidade)
  - envio para **todas as lojas** ou **somente favoritas**
- Gestão de lojas favoritas pelo cliente
- Dashboard da loja com fila de orçamentos recebidos
- Resposta da loja com:
  - valor total
  - prazo de entrega
  - condições de pagamento
  - mensagem adicional
- Notificações no site:
  - loja recebe notificação de novo orçamento
  - cliente recebe notificação quando loja responde
- Design minimalista e responsivo (mobile-first)

## Estrutura de pastas

```text
app/                  # Bootstrap, autenticação, helpers, layout e notificações
assets/css/           # Estilos globais responsivos
database/schema.sql   # Estrutura do banco MySQL
public/               # Raiz pública da aplicação
  user/               # Telas do cliente
  store/              # Telas da loja
```

## Requisitos

- PHP 8.1+ com extensão PDO MySQL
- MySQL 8+
- Servidor web apontando para a pasta `public/`

## Configuração rápida

1. Crie o banco e tabelas:

```bash
mysql -u root -p < database/schema.sql
```

2. Configure variáveis de ambiente (exemplo):

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=orcafacil
export DB_USER=root
export DB_PASS=sua_senha
```

3. Suba um servidor local apontando para `public`:

```bash
php -S 0.0.0.0:8000 -t public
```

4. Acesse:

```text
http://localhost:8000
```

## Fluxo principal

1. Cliente cria orçamento.
2. Sistema envia para:
   - todas as lojas, ou
   - apenas lojas favoritas do cliente.
3. Loja decide responder ou não.
4. Quando a loja responde, cliente recebe notificação no site.

## Próximos passos sugeridos (futuro app Android/iOS)

- Transformar em PWA (ícones, offline básico, instalação)
- Criar API REST autenticada (JWT/Sanctum-like flow)
- Separar frontend para reutilização em app híbrido (Capacitor/Ionic/React Native WebView)
