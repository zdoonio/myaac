# client-downloads — paczki klienta do pobrania ze strony

Strona `/downloads` (system/pages/downloads.php) czyta **`manifest.json`** z tego katalogu
i wystawia każdą pozycję jako plik do pobrania. Katalog leży wewnątrz `myaac/`, a kontener
`site` montuje cały `myaac/`, więc **paczki jadą na VPS razem ze stroną** — nie trzeba
osobnego wolumenu ani reguły w `docker-compose.yml`.

## Jak opublikować paczkę

1. Zbuduj klienta i przygotuj katalog do spakowania (musi zawierać binarkę, `data/`,
   `modules/`, `mods/` i wpisany adres serwera).
2. Spakuj i dopisz do manifestu jednym poleceniem z root repo:

       tools/pack_client.sh windows-x64 ~/build/elysia-client \
           "Elysia Client — Windows (x64)" "Windows 10/11"

3. Plik znajdzie się tutaj, a wpis w `manifest.json`. Strona odświeża się sama —
   **nie edytuj jej ani manifestu ręcznie**.

Paczki są binarne i **nie idą do gita** (`.gitignore`), ale manifest i ten plik — tak:
manifest opisuje, co powinno leżeć obok, a strona zgłasza na czerwono każdą pozycję,
której pliku nie ma. Dzięki temu na VPS od razu widać, czego brakuje.

## Stan na 2026-10-06

- **Linux (x64)** — istnieje binarka naszego forka: `astra-client/AstraClient`
  (zbudowana 2026-10-04). Do spakowania `pack_client.sh`.
- **Windows (x64)** — **brak**. Repo `astra-client` ma przepis builda
  (`.github/workflows/build-vcpkg.yml`, job `build-windows`: windows-2022, MSVC, vcpkg
  `x64-windows-static`), ale wymaga Windowsa albo CI. Pliki `otclient_*_x64.exe`
  w `Elysia_Desktop_client/` są ze **starego, wygaszonego klienta** (D088) i **nie mają**
  funkcji Astry — nie publikować ich jako „naszego" klienta.
- **Klient przeglądarkowy** — już hostowany przez kontener `webclient`; link podaje
  ustawienie `core.webclient_url` (patrz niżej).
