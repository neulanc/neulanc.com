# Run/Build the website

## Build

```bash
npm run build
```

## Run locally (e.g to test)

```bash
npm run dev
```

Subpages will not work, but you can use them with relateive paths with localhost (e.g localhost:4321/faces for faces.neulanc.com)

## Deploy to Hostinger

Upload the contents of `public_html/` to the website's Hostinger document root, including the hidden `.htaccess` file and `404.php`. The generated `404.html` contains the design; the PHP handler ensures unknown URLs keep a genuine HTTP 404 status instead of being replaced by Hostinger's default page.
