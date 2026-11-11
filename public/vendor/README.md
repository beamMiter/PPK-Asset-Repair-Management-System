# Third-party files served by the app

The pages used to load these from CDNs and font services; on a hospital network closed to the internet they lost their
layout, icons, fonts and the chat sound. They are the same files and versions, served from here. To upgrade one, download
the new version into a new `<package>@<version>` folder and change the view that names it.

| Folder | Source | Licence | Used by |
|---|---|---|---|
| `bootstrap@5.3.3` | cdn.jsdelivr.net/npm/bootstrap@5.3.3 (`dist/css`, `dist/js`) | MIT | layouts/app |
| `bootstrap-icons@1.11.3` | cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3 (`font/`) | MIT | layouts/app |
| `tom-select@2.3.1` | cdn.jsdelivr.net/npm/tom-select@2.3.1 (`dist/css`, `dist/js`) | Apache-2.0 | layouts/app |
| `cropperjs@1.6.2` | unpkg.com/cropperjs@1.6.2 (`dist/`) | MIT | profile/edit |
| `font-awesome@6.5.1` | cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1 (`css/all.min.css`, `webfonts/`), same bytes as the SRI hash the page pinned | Font Awesome Free: icons CC BY 4.0, fonts SIL OFL 1.1, code MIT | settings/notifications |
| `fonts/inter` | fonts.googleapis.com, Inter 400 / 500 / 600 (one variable font per character subset) | SIL OFL 1.1 | layouts/app |
| `fonts/material-symbols-outlined` | fonts.googleapis.com, Material Symbols Outlined, opsz 20..48, wght 400, FILL 0, GRAD 0 | Apache-2.0 | layouts/app |
| `fonts/figtree` | fonts.bunny.net, Figtree 400 / 500 / 600 | SIL OFL 1.1 | layouts/guest (reset password) |
| `mixkit` | assets.mixkit.co sound 2358 (the chat's message sound) | Mixkit Sound Effects Free License | partials/chat-fab |

The font services' stylesheets were downloaded as a current browser receives them (woff2, plus woff for Figtree), with their
`url()`s pointed at the files beside them; Inter's three weights share one variable font per subset, kept once.
`tests/Feature/VendoredFilesTest.php` checks that every file a view or stylesheet names is here.
