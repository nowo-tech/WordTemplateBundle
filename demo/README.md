# WordTemplateBundle — demos

Demo applications are **not** shipped in the Composer package (`archive.exclude` includes `/demo`). Clone this repository to run them.

## Symfony 7 (`demo/symfony7`)

FrankenPHP + Docker Compose (PHP **8.2**). Default port **8020**.

```bash
cd demo/symfony7
cp .env.example .env
make up
```

## Symfony 8 (`demo/symfony8`)

Same stack with **Symfony 8** and PHP **8.4**. Default port **8021** so you can run both demos side by side.

```bash
cd demo/symfony8
cp .env.example .env
make up
```

Open the printed URL. The form discovers placeholders in **`public/demo/doc-final-tpl.docx`** (nested keys, `TableRows`, `HtmlContent`) and lets you:

- **Download blank template** — original `.docx` with `${…}` placeholders unchanged (`GET /template`).
- **Download .docx** — filled document from the form (`doc-final-tpl-filled.docx`).
- **Download PDF** — same content via PhpWord + DomPDF (fidelity limits noted in the UI).

Default `HtmlContent` values include inline-styled HTML tables (borders and background colours) as a PHPWord styling reference.

The bundle source is mounted at **`/var/word-template-bundle`** (see each demo’s `docker-compose.yml`).

See [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md) for architecture (Caddyfiles, DNS for Composer, etc.).

## Aggregate commands (from `demo/`)

```bash
make up               # Symfony 7 demo (REQ-DEMO-005 message)
make up8              # Symfony 8 demo
make release-verify   # HTTP 200/302 via symfony7 verify-http
make release-verify8  # same for Symfony 8
make release-check    # update-bundle + test + verify-http (symfony7)
make release-check8   # same for symfony8
```
