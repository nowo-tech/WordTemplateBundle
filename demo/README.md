# WordTemplateBundle — demo

Demo application is **not** shipped in the Composer package (`archive.exclude` includes `/demo`). Clone this repository to run it.

## Symfony 8 (`demo/symfony8`)

FrankenPHP + Docker Compose (Symfony **8**, PHP **8.2**). Default port **8021**.

```bash
cd demo/symfony8
cp .env.example .env
make up
```

Open the printed URL. The form discovers placeholders in **`public/demo/doc-final-tpl.docx`** via `listVariables()` and exercises:

| Feature | Demo control |
|---------|----------------|
| Scalars / dot keys | Text fields (`chapter.number`, `author1.name`, …) |
| Inline word A or B | VIP checkbox → `${client_tier_label}` |
| `HtmlContent` | HTML textareas (`abstract`, `*.body`, …) |
| `TableRows` | Repeating row tables (`row_code`, `ref_text`) |
| `ConditionalBlock` | Checkboxes for `optional_funding` |
| Nested conditional | `funding_detail` inside `optional_funding` |
| `ImageSource` | Path field for `${demo_logo}` |

Downloads:

- **Download blank template** — original `.docx` with `${…}` placeholders unchanged (`GET /template`).
- **Download .docx** — filled document from the form (`doc-final-tpl-filled.docx`).
- **Download PDF** — same content via PhpWord + DomPDF (fidelity limits noted in the UI).

Default `HtmlContent` values include inline-styled HTML tables (borders and background colours) as a PHPWord styling reference.

The bundle source is mounted at **`/var/word-template-bundle`** (see `docker-compose.yml`).

See [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md) for architecture (Caddyfiles, DNS for Composer, etc.).

## Aggregate commands (from `demo/`)

```bash
make up               # demo/symfony8 (REQ-DEMO-005 message)
make release-verify   # HTTP 200/302 via symfony8 verify-http
make release-check    # update-bundle + test + verify-http (symfony8)
```
