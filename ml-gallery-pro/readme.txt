=== ML Gallery Pro ===
Contributors: mlopesdesign
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.26.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin comercial para galerias e albuns com painel proprio, AJAX e estrutura escalavel.

== Description ==

ML Gallery Pro nasce com base instalavel profissional:

* tabelas proprias para galerias, albuns e itens
* menu admin dedicado
* CRUD inicial via AJAX
* criacao de galerias com upload direto e shortcode automatico
* configuracoes globais
* shortcode base para galerias e albuns
* display types base para grid, slideshow e image browser
* tags por imagem com catalogo admin e shortcode global por tag

== Shortcodes ==

Use:

`[ml_gallery id="1"]`

ou:

`[ml_gallery type="album" id="1"]`

ou:

`[ml_gallery type="tag" tag="evento"]`

ou:

`[ml_tag_gallery tag="evento"]`

Compatibilidade legada:

`[ml_gallery_pro gallery="1"]`

ou:

`[ml_gallery_pro album="1"]`

== Changelog ==

= 0.26.11 =
* Versao de teste do auto-updater. Sem alteracoes funcionais.

= 0.26.10 =
* Corrige pipeline de atualizacao automatica.
* Corrige after_install para lidar com source code zip do GitHub.

= 0.26.9 =
* Adiciona GitHub Updater: atualizacao automatica via WP Admin.

= 0.26.8 =
* Corrige lightbox: navegacao completa com prev/next, teclado e swipe.

= 0.26.7 =
* Corrige bloco Gutenberg com useBlockProps.

= 0.26.6 =
* Corrige categoria do bloco para media.

= 0.26.5 =
* Adiciona botoes Add Gallery e Add Album no Gutenberg.

= 0.26.4 =
* Corrige docblocks e sanitizacao de entrada.
