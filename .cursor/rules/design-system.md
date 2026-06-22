---
description: Design system EvenTche — identidade visual, tokens e componentes para landing page e painel administrativo
globs: **/*.{tsx,ts,jsx,js,vue,css,scss,html}
alwaysApply: true
---

# EvenTche — Design System

Referência oficial de UI/UX da plataforma EvenTche. Use ao criar a **landing page**, **painel administrativo** e o layout dos emails. Mantenha consistência visual, tipográfica e de componentes.

---

## 1. Identidade da marca

| Elemento | Regra |
|----------|-------|
| Nome | **even** (sans-serif bold) + **Tche** (script, cor laranja) |
| Tom | Moderno, clean, energético, confiável |
| Idioma | Português (pt-BR) |
| Estilo | Cantos arredondados, sombras suaves, alto contraste navy + laranja |

### Logotipo

```
evenTche
^^^^    ^^^^
Montserrat 800   Pacifico (laranja #ff6600)
```

- **Ícone do app:** `Gemini_Generated_Image_jqhzpzjqhzpzjqhz.png` — favicon, header, sidebar, avatar da marca
- **Logotipo principal:** `Gemini_Generated_Image_wamzy5wamzy5wamz.png` — materiais com fundo claro
- **Padrão decorativo:** `file.svg` — fundos hero/banner (opacidade 0.15–0.35)
- **Nunca** inverter ou distorcer o logo; em fundos escuros, usar ícone + tipografia (não o PNG com fundo branco)

```html
<!-- Logo em header/sidebar -->
<a class="brand">
  <img src="...jqhzpzjqhzpzjqhz.png" alt="" width="36" height="36" />
  <span>even<span class="brand__accent">Tche</span></span>
</a>
```

```css
.brand__accent { font-family: var(--font-accent); color: var(--orange); }
```

---

## 2. Tokens de cor

```css
:root {
  /* Primárias */
  --blue: #003366;        /* Fundos escuros, sidebar, títulos, texto principal em dark UI */
  --blue-light: #004d99;  /* Hover em nav, badges informativos */
  --orange: #ff6600;      /* CTA primário, destaques, links de ação */
  --orange-hover: #e55a00;
  --teal: #1abc9c;        /* Sucesso, status ativo, badges positivos */
  --yellow: #ffc107;      /* Alertas, promoções, destaque secundário */

  /* Neutros */
  --gray: #f2f2f2;        /* Fundos alternados, divisores */
  --lavender: #f2f2ff;    /* Fundos de seção, hover sutil em tabelas */
  --white: #ffffff;       /* Cards, inputs, conteúdo principal */
  --text: #1a2b3c;        /* Texto corpo */
  --text-muted: #6b7c8f;  /* Labels, placeholders, metadados */
}
```

### Uso semântico

| Contexto | Cor |
|----------|-----|
| CTA primário | `--orange` |
| CTA secundário / outline | `--blue` ou borda `rgba(0,51,102,0.2)` |
| Fundo app (admin) | `--gray` ou `--lavender` |
| Sidebar / topbar escuro | `--blue` |
| Card / painel | `--white` |
| Sucesso | `--teal` |
| Aviso | `--yellow` (texto escuro) |
| Erro | `#e74c3c` (derivado; não na paleta base) |
| Info | `--blue-light` |

### Gradientes aprovados

```css
/* Hero, banners, cards de evento */
linear-gradient(135deg, var(--blue) 0%, var(--teal) 100%)
linear-gradient(135deg, var(--orange) 0%, var(--yellow) 100%)
linear-gradient(135deg, var(--blue) 0%, #002244 100%)  /* CTA/footer */
```

---

## 3. Tipografia

**Fontes (Google Fonts):**
```
Montserrat: 400, 500, 600, 700, 800
Pacifico: 400 (somente acento de marca)
```

```html
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Pacifico&display=swap" rel="stylesheet" />
```

| Uso | Font | Peso | Tamanho |
|-----|------|------|---------|
| H1 (hero) | Montserrat | 800 | clamp(2.5rem, 6vw, 4rem) |
| H2 (seção) | Montserrat | 800 | clamp(1.75rem, 4vw, 2.25rem) |
| H3 (card) | Montserrat | 700 | 1.0625rem |
| Corpo | Montserrat | 400–500 | 1rem / 0.875rem |
| Eyebrow / label | Montserrat | 700 | 0.8125rem, uppercase, letter-spacing 0.08em |
| Acento marca | Pacifico | 400 | proporcional ao contexto |
| KPI / stat | Montserrat | 800 | clamp(1.75rem, 4vw, 2.5rem) |

- `line-height`: 1.6 (corpo), 1.1–1.2 (títulos)
- `letter-spacing`: -0.02em em títulos grandes
- Pacifico **somente** em "Tche" e frases de destaque pontuais — nunca em parágrafos longos

---

## 4. Espaçamento e layout

```css
--radius: 16px;       /* Cards, modais */
--radius-sm: 10px;    /* Inputs, badges internos */
--radius-lg: 24px;    /* Banners, seções hero */
--radius-full: 9999px; /* Botões, pills, search bar */

--shadow: 0 4px 24px rgba(0, 51, 102, 0.08);
--shadow-lg: 0 12px 48px rgba(0, 51, 102, 0.14);
--transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
```

| Token | Valor | Uso |
|-------|-------|-----|
| Container max | 1200px | Landing page |
| Container admin | 1400px | Painel administrativo |
| Padding container | 24px | Lateral |
| Seção vertical | 96px (64px mobile) | Entre blocos |
| Gap grid | 24px | Cards, formulários |
| Padding card | 20–24px | Interno de cards |

### Grid

- Landing: `repeat(auto-fill, minmax(260px, 1fr))` para cards de evento
- Admin dashboard: `repeat(auto-fit, minmax(220px, 1fr))` para KPIs
- Admin conteúdo: sidebar fixa 260px + área flexível

---

## 5. Componentes — Landing page

### Botões

```html
<a class="btn btn--primary">Criar conta</a>
<a class="btn btn--ghost">Login</a>
<a class="btn btn--outline-light">Sou produtor</a>
<a class="btn btn--primary btn--lg">Aproveitar agora</a>
```

| Variante | Uso |
|----------|-----|
| `btn--primary` | Ação principal (laranja) |
| `btn--ghost` | Ação terciária em fundo escuro |
| `btn--outline-light` | Ação secundária em fundo escuro |
| `btn--lg` | CTAs de destaque |

Hover primário: `translateY(-1px)` + `box-shadow: 0 6px 20px rgba(255,102,0,0.35)`

### Search bar

- Fundo branco, `border-radius: full`, botão de busca laranja à direita
- Focus: glow laranja `box-shadow: 0 0 0 3px rgba(255,102,0,0.15)`

### Category pills

```html
<a class="category-pill category-pill--blue">Shows</a>
<a class="category-pill category-pill--orange">Festivais</a>
<a class="category-pill category-pill--teal">Teatro</a>
<a class="category-pill category-pill--yellow">Esportes</a>
```

### Event card

```
event-card
├── event-card__image (+ --1 a --4 gradientes)
│   └── event-card__tag
└── event-card__body
    ├── event-card__date (laranja, uppercase)
    ├── event-card__title (navy, bold)
    ├── event-card__location (muted)
    └── event-card__footer
        ├── event-card__price
        └── event-card__btn
```

Hover: `translateY(-6px)` + `--shadow-lg`

### Section header

```html
<div class="section__header">
  <div>
    <span class="section__eyebrow">Em destaque</span>
    <h2 class="section__title">Eventos populares</h2>
  </div>
  <a class="section__link">Ver todos →</a>
</div>
```

### Stat (KPI)

```html
<div class="stat">
  <span class="stat__value">2.000+</span>
  <span class="stat__label">Eventos ativos</span>
</div>
```

---

## 6. Componentes — Painel administrativo

Estender o design system da landing para o admin. **Mesmos tokens**, layout funcional tipo dashboard.

### Estrutura do layout

```
┌─────────────────────────────────────────────┐
│ admin-layout                                │
├──────────┬──────────────────────────────────┤
│ sidebar  │ admin-main                       │
│ (navy)   │ ├── admin-topbar (white/blur)    │
│ 260px    │ └── admin-content (gray/lavender)│
└──────────┴──────────────────────────────────┘
```

```css
.admin-layout { display: flex; min-height: 100vh; background: var(--gray); }
.admin-sidebar {
  width: 260px; background: var(--blue); color: var(--white);
  padding: 24px 16px; flex-shrink: 0;
}
.admin-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.admin-topbar {
  background: var(--white); padding: 16px 32px;
  box-shadow: var(--shadow); display: flex; align-items: center; justify-content: space-between;
}
.admin-content { padding: 32px; flex: 1; }
```

### Sidebar

- Fundo `--blue`, texto branco com opacidade 0.85
- Logo no topo (ícone + even**Tche**)
- Nav items: `padding 12px 16px`, `border-radius: full`
- Item ativo: `background: rgba(255,255,255,0.12)`, texto branco
- Item hover: `background: rgba(255,255,255,0.08)`
- Ícones SVG inline, 20px, `stroke` (não preenchidos)

```html
<nav class="sidebar-nav">
  <a class="sidebar-nav__item sidebar-nav__item--active" href="/dashboard">
    <svg>...</svg> Dashboard
  </a>
  <a class="sidebar-nav__item" href="/eventos">Eventos</a>
  <a class="sidebar-nav__item" href="/ingressos">Ingressos</a>
  <a class="sidebar-nav__item" href="/financeiro">Financeiro</a>
  <a class="sidebar-nav__item" href="/configuracoes">Configurações</a>
</nav>
```

### Cards de painel

Reutilizar padrão `event-card` / `stat`:

```html
<div class="panel-card">
  <div class="panel-card__header">
    <h3 class="panel-card__title">Vendas do mês</h3>
    <span class="badge badge--teal">+12%</span>
  </div>
  <p class="panel-card__value">R$ 48.320</p>
  <p class="panel-card__meta">vs. mês anterior</p>
</div>
```

```css
.panel-card {
  background: var(--white); border-radius: var(--radius);
  padding: 24px; box-shadow: var(--shadow);
}
.panel-card__value { font-size: 2rem; font-weight: 800; color: var(--blue); }
.panel-card__meta { font-size: 0.8125rem; color: var(--text-muted); }
```

### Tabelas de dados

```css
.data-table { width: 100%; background: var(--white); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
.data-table th {
  text-align: left; padding: 14px 20px; font-size: 0.75rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted);
  background: var(--lavender); border-bottom: 1px solid var(--gray);
}
.data-table td { padding: 16px 20px; border-bottom: 1px solid var(--gray); font-size: 0.875rem; }
.data-table tr:hover td { background: var(--lavender); }
```

### Formulários

```css
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 0.8125rem; font-weight: 600; color: var(--blue); margin-bottom: 6px; }
.form-input {
  width: 100%; padding: 12px 16px; border: 2px solid var(--gray);
  border-radius: var(--radius-sm); font-family: var(--font); font-size: 0.9375rem;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.form-input:focus {
  outline: none; border-color: var(--orange);
  box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.12);
}
.form-input::placeholder { color: var(--text-muted); }
```

Botões de formulário: `btn--primary` (salvar), variante secundária com borda navy (cancelar).

### Badges de status

```html
<span class="badge badge--teal">Ativo</span>
<span class="badge badge--orange">Pendente</span>
<span class="badge badge--yellow">Rascunho</span>
<span class="badge badge--blue">Publicado</span>
```

```css
.badge {
  display: inline-flex; padding: 4px 12px; border-radius: var(--radius-full);
  font-size: 0.75rem; font-weight: 600;
}
.badge--teal   { background: rgba(26,188,156,0.15); color: #12806a; }
.badge--orange { background: rgba(255,102,0,0.12); color: var(--orange-hover); }
.badge--yellow { background: rgba(255,193,7,0.2); color: #9a7b00; }
.badge--blue   { background: rgba(0,51,102,0.1); color: var(--blue); }
```

### Alertas

```html
<div class="alert alert--success">Ingresso confirmado com sucesso.</div>
<div class="alert alert--warning">Evento com baixo estoque.</div>
<div class="alert alert--error">Falha ao processar pagamento.</div>
```

| Tipo | Fundo | Borda esquerda |
|------|-------|----------------|
| success | `rgba(26,188,156,0.1)` | `--teal` 4px |
| warning | `rgba(255,193,7,0.15)` | `--yellow` 4px |
| error | `rgba(231,76,60,0.1)` | `#e74c3c` 4px |
| info | `rgba(0,77,153,0.1)` | `--blue-light` 4px |

### Empty state

```html
<div class="empty-state">
  <img src="...jqhzpzjqhzpzjqhz.png" alt="" width="64" />
  <h3>Nenhum evento cadastrado</h3>
  <p>Crie seu primeiro evento para começar a vender ingressos.</p>
  <a class="btn btn--primary">Criar evento</a>
</div>
```

Centralizado, ícone com opacidade 0.6, texto muted, CTA laranja.

---

## 7. Convenção de nomenclatura (BEM)

```
bloco__elemento--modificador
```

| Bloco | Elementos comuns |
|-------|------------------|
| `header` | `__inner`, `__logo`, `__nav`, `__actions` |
| `event-card` | `__image`, `__body`, `__title`, `__footer` |
| `panel-card` | `__header`, `__title`, `__value`, `__meta` |
| `sidebar-nav` | `__item`, `__item--active` |
| `form` | `__group`, `__label`, `__input`, `__error` |
| `data-table` | `__actions`, `__row--highlighted` |

- Classes utilitárias com prefixo do bloco, não globais genéricos (`text-center` ok; `blue` não)
- Estados via modificador (`--active`, `--scrolled`, `--open`) ou classe `visible` para animações

---

## 8. Animações e interação

```css
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.reveal { opacity: 0; transform: translateY(30px); transition: opacity 0.6s, transform 0.6s; }
.reveal.visible { opacity: 1; transform: translateY(0); }
```

| Interação | Comportamento |
|-----------|---------------|
| Cards | hover: `translateY(-4px a -6px)` + shadow-lg |
| Botões primários | hover: `translateY(-1px)` + glow laranja |
| Links de ação | cor `--orange`, sem sublinhado |
| Header fixo | scroll > 40px: `backdrop-filter: blur(12px)`, fundo navy 95% |
| Focus inputs | ring laranja 3px |
| Transições | sempre `--transition` (250ms) |

Evitar animações excessivas no admin; priorizar feedback funcional (loading, toast, skeleton).

---

## 9. Responsividade

| Breakpoint | Comportamento |
|------------|---------------|
| ≤ 480px | 1 coluna, search empilhado, stats 2 colunas |
| ≤ 768px | Menu hamburger, sidebar vira drawer, tabelas com scroll horizontal |
| ≤ 1024px | Grids 2 colunas, about/promos empilhados |
| > 1024px | Layout completo |

Admin mobile: sidebar off-canvas com overlay `rgba(0,51,102,0.5)`.

---

## 10. Acessibilidade

- `lang="pt-BR"` no `<html>`
- Contraste mínimo WCAG AA (navy + branco, laranja + branco ok)
- `aria-label` em botões só com ícone
- `aria-expanded` em menus
- `role="search"` em buscas
- Foco visível em todos os interativos (ring laranja)
- Textos alternativos em imagens; decorativas com `alt=""` e `aria-hidden="true"`

---

## 11. Regras para o agente (DO / DON'T)

### ✅ FAZER

- Usar tokens CSS (`var(--blue)`, etc.) — nunca hardcodar cores fora da paleta
- Manter Montserrat como fonte principal; Pacifico só na marca
- Cards brancos sobre fundo `--gray` ou `--lavender`
- CTAs principais sempre laranja; secundários navy/outline
- Reutilizar `stat`, `panel-card`, `badge`, `btn` entre landing e admin
- Sidebar admin em navy; conteúdo em fundo claro
- Ícones SVG com stroke, 20–24px, `currentColor`

### ❌ NÃO FAZER

- Não usar roxo, vermelho ou verde fora dos tokens semânticos
- Não usar Pacifico em tabelas, formulários ou labels
- Não colocar o PNG do logo (fundo branco) sobre fundo navy sem container
- Não usar cantos retos (border-radius 0) em botões e cards
- Não criar sombras pesadas pretas; usar sombras com tint navy
- Não misturar outra família tipográfica
- Não usar gradientes radicais fora dos 3 aprovados

---

## 12. Referência de arquivos

| Arquivo | Conteúdo |
|---------|----------|
| `css/styles.css` | Implementação completa dos tokens e componentes da landing |
| `index.html` | Estrutura e exemplos de uso |
| `js/main.js` | Scroll, menu mobile, reveal animations |
| `Gemini_Generated_Image_jqhzpzjqhzpzjqhz.png` | Ícone / favicon |
| `Gemini_Generated_Image_wamzy5wamzy5wamz.png` | Logotipo principal |
| `Gemini_Generated_Image_bt1w6jbt1w6jbt1w.png` | Brand kit (referência visual) |
| `file.svg` | Padrão de ondas para backgrounds |

Ao criar o painel administrativo, **extraia os tokens para um arquivo compartilhado** (ex.: `shared/tokens.css` ou theme do framework) e estenda com os componentes da seção 6.
