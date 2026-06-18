<!-- Print only the invoice content — hide sidebar, modal chrome, and buttons -->
<style>
@media print {
  body * { visibility: hidden; }
  #invoicePrintArea, #invoicePrintArea * { visibility: visible; }
  #invoicePrintArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; }
}
</style>

<!-- Invoice Modal -->
<div class="modal-overlay" id="invoiceModal">
  <div class="modal-box" style="max-width:640px">
    <div class="modal-header">
      <i class="bi bi-receipt" style="color:#7c3aed;font-size:20px"></i>
      <span class="modal-title">Ride Invoice</span>
      <button class="modal-close" onclick="Modal.close('invoiceModal')"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body" style="padding:0;background:#fff" id="invBody"></div>
    <div class="modal-footer">
      <button class="btn-glass" onclick="Modal.close('invoiceModal')"><i class="bi bi-x"></i> Close</button>
      <button class="btn-primary-glass" id="invDownloadBtn" onclick="downloadInvoicePdf()"><i class="bi bi-download"></i> Download PDF</button>
    </div>
  </div>
</div>
