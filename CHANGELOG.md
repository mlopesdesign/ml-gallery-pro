# Changelog

All notable changes to **ML Gallery Pro** are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/).

The WordPress.org changelog (used by the WP admin updater) lives in
[`ml-gallery-pro/readme.txt`](./ml-gallery-pro/readme.txt) and stays in lockstep
with this file.

---

## [0.26.15] - 2026-07-02

### Fixed
- Indevida execução de rotinas de instalação/upgrade em requisições públicas.
- `cleanup_old_installations()` e escritas via filesystem durante frontend normal.
- Concorrência em upgrade com lock curto e controle de versão instalada.
- Sanitização de parâmetros públicos `mlgp_album_view_*` e `mlgp_page_gallery_*`.
- Carga causada por crawlers em páginas com múltiplas galerias.
- Flush de rewrite indevido separado do controle de upgrade.

### Compatibility
- Preserva ML Carousel Gallery Pro.
- Preserva shortcodes existentes.
- Preserva capas, títulos, álbuns, galerias, URLs públicas e estrutura de dados.
- Não altera saída visual validada.

---

## [0.26.14] - 2026-06-27

### Fixed
- Persistência da capa escolhida ao salvar qualquer informação da galeria.
- Updater GitHub herdado: cache obsoleto eliminado, integração com "Verificar novamente".
- Compatibilidade do updater com PHP 7.4 e preservação do estado de ativação.

### Compatibility
- Mantém slug, pasta, banco, frontend, editor, licenciamento.

---

## [0.26.13] - 2026-06-27

### Fixed
- Capa voltando para a primeira imagem após salvar título, descrição, status, layout ou configurações visuais.
- `cover_item_id` e `cover_attachment_id` preservados em salvamentos parciais.
- Fallback de capa continua restrito ao caso de remoção da imagem usada.

---

## [0.26.12] - 2026-06-04

### Fixed
- Lightbox exibindo apenas fundo escuro sem a imagem.
- Isolamento da imagem do lightbox contra CSS global e lazy-load do tema.
- Carregamento visual, tratamento de erro e fallback automático para imagem grande.

---

## [0.26.11] - 2026-05-27

### Internal
- Versão de teste do auto-updater. Sem alterações funcionais.

---

## [0.26.10] - 2026-05-27

### Fixed
- Pipeline de atualização automática: ZIP anexado corretamente como asset da release.
- `after_install` renomeia a pasta extraída do source archive para o slug correto.
- Primeira versão com motor de atualização funcional de ponta a ponta.

### Note
- **Instalação manual obrigatória desta versão.** Próximas versões chegam via WP Admin > Plugins > Atualizações.

---

## [0.26.9] - 2026-05-27

### Added
- GitHub Updater: plugin aparece em WP Admin > Plugins > Atualizações quando há nova versão.
- Atualização automática com 1 clique direto pelo painel WordPress.
- Cache de 6 horas quando há versão, 15 min em caso de erro.
- Fallback de ZIP: asset da Release como primário, ZIP do GitHub como fallback.

---

## [0.26.8] - 2026-05-27

### Fixed
- Lightbox: navegação completa (Anterior / Próximo) e contador X/Y.
- Teclado: setas ←/→, Esc fecha.
- Touch: swipe no mobile.
- Visual: stage + botões prev/next com SVG.

---

## [0.26.7] - 2026-05-27

### Fixed
- Bloco Gutenberg usa `useBlockProps`: selecionável, movível, duplicável, removível.
- Inserção duplicada: seleciona galeria/álbum atualiza o bloco atual.
- `BlockControls` mostra "Change Gallery/Album" só após seleção.
- Suporte a alinhamentos `wide` e `full`.

---

## Earlier versions

Veja [`ml-gallery-pro/readme.txt`](./ml-gallery-pro/readme.txt) para histórico completo (≥0.1.0).
