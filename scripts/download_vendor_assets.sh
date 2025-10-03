#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENDOR_DIR="$BASE_DIR/src/static-assets/vendor"

fetch() {
  local url="$1"
  local dest="$2"
  echo "Downloading ${url} -> ${dest}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "$url" -o "$dest"
}

# jQuery core and migrate
fetch "https://code.jquery.com/jquery-3.6.0.min.js" "$VENDOR_DIR/jquery/jquery-3.6.0.min.js"
fetch "https://code.jquery.com/jquery-migrate-1.2.1.min.js" "$VENDOR_DIR/jquery-migrate/jquery-migrate-1.2.1.min.js"

# Bootstrap 4.6
fetch "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js" "$VENDOR_DIR/bootstrap/js/bootstrap.bundle.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" "$VENDOR_DIR/bootstrap/css/bootstrap.min.css"

# Bootbox
fetch "https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.5.2/bootbox.min.js" "$VENDOR_DIR/bootbox/bootbox.min.js"

# OverlayScrollbars
fetch "https://cdnjs.cloudflare.com/ajax/libs/overlayscrollbars/1.13.1/js/jquery.overlayScrollbars.min.js" "$VENDOR_DIR/overlayscrollbars/js/OverlayScrollbars.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/overlayscrollbars/1.13.1/css/OverlayScrollbars.min.css" "$VENDOR_DIR/overlayscrollbars/css/OverlayScrollbars.min.css"

# Font Awesome 5.15.4
fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" "$VENDOR_DIR/fontawesome/css/all.min.css"
for font in fa-brands-400 fa-regular-400 fa-solid-900; do
  fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/${font}.woff2" "$VENDOR_DIR/fontawesome/webfonts/${font}.woff2"
  fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/${font}.woff" "$VENDOR_DIR/fontawesome/webfonts/${font}.woff"
  fetch "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/${font}.ttf" "$VENDOR_DIR/fontawesome/webfonts/${font}.ttf"
done

# Pace
fetch "https://cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js" "$VENDOR_DIR/pace/js/pace.min.js"

# SweetAlert2 8.19.1
fetch "https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/8.19.1/sweetalert2.min.js" "$VENDOR_DIR/sweetalert2/sweetalert2.min.js"

# Slick carousel 1.8.1
fetch "https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js" "$VENDOR_DIR/slick/js/slick.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css" "$VENDOR_DIR/slick/css/slick.min.css"
fetch "https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css" "$VENDOR_DIR/slick/css/slick-theme.min.css"

# Moment.js 2.24.0
fetch "https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js" "$VENDOR_DIR/moment/moment.min.js"

# Daterangepicker 3.0.5
fetch "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js" "$VENDOR_DIR/daterangepicker/js/daterangepicker.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.css" "$VENDOR_DIR/daterangepicker/css/daterangepicker.min.css"

# Select2 4.0.13
fetch "https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js" "$VENDOR_DIR/select2/js/select2.full.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" "$VENDOR_DIR/select2/css/select2.min.css"

# FullCalendar 5.11.5
fetch "https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.js" "$VENDOR_DIR/fullcalendar/js/main.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.11.5/main.min.css" "$VENDOR_DIR/fullcalendar/css/main.min.css"

# jQuery UI 1.12.1
fetch "https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" "$VENDOR_DIR/jquery-ui/js/jquery-ui.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.min.css" "$VENDOR_DIR/jquery-ui/css/jquery-ui.min.css"

# Summernote 0.8.18
fetch "https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-bs4.min.js" "$VENDOR_DIR/summernote/js/summernote-bs4.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-bs4.min.css" "$VENDOR_DIR/summernote/css/summernote-bs4.min.css"

# DataTables 1.10.21
fetch "https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js" "$VENDOR_DIR/datatables/js/jquery.dataTables.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap4.min.js" "$VENDOR_DIR/datatables/js/dataTables.bootstrap4.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap4.min.css" "$VENDOR_DIR/datatables/css/dataTables.bootstrap4.min.css"

# PDFMake 0.2.6
fetch "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js" "$VENDOR_DIR/pdfmake/js/pdfmake.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/vfs_fonts.js" "$VENDOR_DIR/pdfmake/js/vfs_fonts.js"

# Chart.js
fetch "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js" "$VENDOR_DIR/chartjs/Chart.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" "$VENDOR_DIR/chartjs/chart.umd.min.js"

# jQuery Knob 1.2.13
fetch "https://cdnjs.cloudflare.com/ajax/libs/jQuery-Knob/1.2.13/jquery.knob.min.js" "$VENDOR_DIR/knob/jquery.knob.min.js"

# Uppy 1.24.0
fetch "https://cdnjs.cloudflare.com/ajax/libs/uppy/1.24.0/uppy.min.js" "$VENDOR_DIR/uppy/js/uppy.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/uppy/1.24.0/uppy.min.css" "$VENDOR_DIR/uppy/css/uppy.min.css"

# Spectrum color picker 1.8.1
fetch "https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js" "$VENDOR_DIR/spectrum/spectrum.min.js"
fetch "https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css" "$VENDOR_DIR/spectrum/spectrum.min.css"

