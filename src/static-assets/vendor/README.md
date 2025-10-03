# Vendor asset placeholders

The files in this directory are populated by running `scripts/download_vendor_assets.sh`.
This repository snapshot does not bundle third-party libraries so that offline builds can
recreate the exact versions needed without relying on a CDN at runtime. Execute the script
before building the frontend to fetch all required CSS/JS bundles into the paths referenced
from the Twig templates.
