# CT Custom Builders

Production-ready website for **https://ctcustombuilders.com**, serving Bozeman, Big Sky, and Montana’s Gallatin Valley.

## Hostinger deployment

Use a **Custom PHP/HTML** website on Hostinger web or cloud hosting.

1. Open the website's dashboard, then **Advanced → Git**.
2. Connect GitHub and authorize access to **nerdicom/ctcb**.
3. Choose this repository and branch **main**.
4. Set the deployment directory to **public_html** and deploy.
5. Enable automatic deployment if future changes to `main` should publish automatically.

`index.html` and the `assets`, `services`, and `areas` folders belong directly in `public_html`. There is no build command, package installation, Node.js runtime, or database requirement. The consultation form uses the PHP runtime included with Hostinger Custom PHP/HTML hosting (PHP 7.4 or newer).

Connect the domain to the Hostinger website and enable its SSL certificate/HTTPS. The site already uses `https://ctcustombuilders.com` for canonical URLs, structured data, and the sitemap.

Hostinger's current guide: https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/

## Pages

- `/` — homepage
- `/services/custom-homes/`
- `/services/remodels-additions/`
- `/services/general-contracting/`
- `/areas/bozeman/`
- `/areas/big-sky/`
- `/areas/gallatin-valley/`

## Content updates

Each route is a standalone HTML page. Shared styles and mobile navigation are in `assets/site.css` and `assets/site.js`. All images are included locally in optimized WebP format.

The current telephone placeholder is **(406) 000-0000** and is not a clickable telephone number. The email address is **info@ctcustombuilders.com**, exactly as provided. Replace the placeholder and confirm the email mailbox before promoting the site.

Project inquiry buttons open an accessible consultation request dialog. The form collects name, project address, phone, email, and a project description, then sends the inquiry to `info@ctcustombuilders.com` through `api/consultation.php`. Customers are contacted to arrange a time; the form does not book a calendar slot. Direct email links remain available.

Architectural and craftsmanship images are labeled as illustrative concepts. There are no fabricated completed projects, reviews, certifications, or years in business.

## Search setup

The site includes unique page titles and descriptions, canonical links, Organization/Service/Breadcrumb structured data, readable static content, responsive images, `robots.txt`, and `sitemap.xml`.

After public launch, verify the domain in Google Search Console and submit `https://ctcustombuilders.com/sitemap.xml`. Add verified business details and the real phone number when available. Search Console and Google Business Profile setup are separate from publishing the website.

## Brand assets

The matching architectural CT identity is installed in the header and footer of every page.

- `assets/ctcb-logo-v2-dark.svg` — complete logo for light backgrounds.
- `assets/ctcb-logo-v2-light.svg` — complete logo for dark backgrounds.
- `assets/ctcb-mark-v2.png` — square monogram.
- `favicon.ico`, PNG favicon sizes, and Apple touch icon — matching browser and home-screen icons.

The original CT mark was produced with built-in image generation. Website logo lockups use that mark with a readable wordmark, and favicons are resized exports from the same master. SVG lockups include an embedded raster monogram and vector text. Versioned asset names and stylesheet references refresh the branding after deployment.

## Consultation form

- Endpoint: `POST /api/consultation.php`; only the fixed company mailbox is a recipient. Replies go to the validated customer email.
- Native dialog provides keyboard focus containment and Escape-to-close; form details remain in place after failures.
- Client/server validation, size limits, origin checks, a honeypot, and server-side rate limiting protect the endpoint.
- Rate-limit metadata contains hashed visitor identifiers and timestamps in the hosting server temporary directory outside the website. Project details are not saved to disk or browser storage.
- The endpoint uses Hostinger's server mail transport. A successful response means the mail service accepted the message, not proof of inbox receipt. Confirm the mailbox exists and send a real inquiry to check delivery after launch. Authenticated SMTP is recommended if deliverability needs improvement. No SMTP password or mailbox credential is stored in the repository.
- Delivery failures return an error and keep the customer's entries available for retry or direct email.
