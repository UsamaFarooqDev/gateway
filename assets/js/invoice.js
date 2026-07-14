/* PowerCabs Admin — Shared Ride Invoice Generator (preview + PDF download) */

const INVOICE_COMMISSION_PCT = 10;
let _invoiceData      = {};
let _currentInvoiceId = null;
let _driverMode       = false;

function setInvoiceData(map, driverMode) {
  _invoiceData  = map || {};
  _driverMode   = !!driverMode;
}

function escHtml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-IE', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function showInvoice(id) {
  const r = _invoiceData[id];
  if (!r) { Toast.show('Ride not found.', 'error'); return; }
  _currentInvoiceId = id;
  document.getElementById('invBody').innerHTML = buildInvoiceHtml(r, id);
  Modal.open('invoiceModal');
}

function invLine(label, value, valueColor) {
  return `<tr>
    <td style="padding:5px 0;color:#64748B;width:160px;font-size:12.5px">${escHtml(label)}</td>
    <td style="padding:5px 0;font-weight:500;font-size:12.5px;${valueColor ? ('color:' + valueColor + ';') : ''}">${escHtml(value)}</td>
  </tr>`;
}

function buildInvoiceHtml(r, id) {
  if (_driverMode) return buildDriverInvoiceHtml(r, id);

  const fare        = parseFloat(r.fare) || 0;
  const commission  = fare * INVOICE_COMMISSION_PCT / 100;
  const driverEarns = fare - commission;
  const invoiceNo   = 'PC-' + id.slice(0, 8).toUpperCase();

  const passengerBusinessBlock = (r.passenger_business_name || r.passenger_tax_number)
    ? `<div style="margin-top:6px;padding-top:6px;border-top:1px solid #E2E8F0">
        ${r.passenger_business_name ? `<div style="font-size:12px;font-weight:600;color:#1a1a2e">${escHtml(r.passenger_business_name)}</div>` : ''}
        ${r.passenger_tax_number    ? `<div style="font-size:11.5px;color:#64748B">Tax/VAT: ${escHtml(r.passenger_tax_number)}</div>` : ''}
       </div>`
    : '';

  return `
  <div id="invoicePrintArea" style="font-family:'Poppins',Arial,sans-serif;color:#1a1a2e;padding:32px;background:#fff">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #F37A20;padding-bottom:18px;margin-bottom:22px">
      <div>
        <img src="assets/img/logo.png" alt="PowerCabs" style="height:48px;object-fit:contain">
        ${_pcCompanyBlock()}
      </div>
      <div style="text-align:right">
        <div style="font-size:16px;font-weight:700">RIDE INVOICE</div>
        <div style="font-size:12px;color:#64748B;margin-top:4px">Invoice No: <b>${escHtml(invoiceNo)}</b></div>
        <div style="font-size:12px;color:#64748B">Date Issued: ${escHtml(fmtDate(r.created_at))}</div>
        <div style="font-size:11px;color:#94A3B8;margin-top:2px">Ride ID: ${escHtml(id)}</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px">
      <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px 16px">
        <div style="font-size:11px;font-weight:700;color:#F37A20;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Billed To (Passenger)</div>
        <div style="font-size:13.5px;font-weight:600">${escHtml(r.passenger_name || '—')}</div>
        <div style="font-size:12px;color:#64748B;margin-top:4px">${escHtml(r.passenger_email || '—')}</div>
        <div style="font-size:12px;color:#64748B">${escHtml(r.passenger_phone || '—')}</div>
        ${passengerBusinessBlock}
      </div>
      <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px 16px">
        <div style="font-size:11px;font-weight:700;color:#F37A20;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Service Provided By (Driver)</div>
        <div style="font-size:13.5px;font-weight:600">${escHtml(r.driver_name || 'Unassigned')}</div>
        <div style="font-size:11px;color:#94A3B8;margin-top:4px;word-break:break-all">Driver ID: ${escHtml(r.driver_id || '—')}</div>
        <div style="font-size:12px;color:#64748B;margin-top:2px">${escHtml(r.driver_email || '—')}</div>
        <div style="font-size:12px;color:#64748B">${escHtml(r.driver_phone || '—')}</div>
      </div>
    </div>

    <div style="margin-bottom:22px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;border-bottom:1px solid #E2E8F0;padding-bottom:6px">Trip Details</div>
      <table style="width:100%;border-collapse:collapse">
        ${invLine('Date & Time', fmtDate(r.created_at))}
        ${invLine('Pickup', r.pickup_addr || '—')}
        ${invLine('Destination', r.dest_addr || '—')}
        ${invLine('Distance', r.distance_km ? parseFloat(r.distance_km).toFixed(2) + ' km' : '—')}
        ${invLine('Duration', r.duration_min ? Math.round(r.duration_min) + ' min' : '—')}
      </table>
    </div>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px 18px;margin-bottom:18px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px">Fare Breakdown</div>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0">
        <span style="color:#64748B">Total Fare Charged</span><span style="font-weight:600">€${fare.toFixed(2)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0">
        <span style="color:#64748B">PowerCabs Commission (${INVOICE_COMMISSION_PCT}%)</span><span style="font-weight:600;color:#dc2626">- €${commission.toFixed(2)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0">
        <span style="color:#64748B">Driver Net Earnings</span><span style="font-weight:600;color:#16a34a">€${driverEarns.toFixed(2)}</span>
      </div>
      <div style="border-top:2px solid #1a1a2e;margin:10px 0 8px"></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700">
        <span>Total Charged to Passenger</span><span style="color:#F37A20">€${fare.toFixed(2)}</span>
      </div>
    </div>

    <div style="text-align:center;border-top:1px solid #E2E8F0;padding-top:14px;font-size:11px;color:#94A3B8">
      <div>This is a computer-generated invoice and does not require a signature.</div>
      <div style="margin-top:3px">PowerCabs · powercabs.ie · support@powercabs.ie</div>
    </div>
  </div>`;
}

/* ─── Driver-mode invoice templates ────────────────────────────────
   Used when a specific driver is filtered in Invoice Management.
   Matches the PowerCabs weekly driver statement format. ──────────── */

function _vehicleLabel(vt) {
  try {
    const arr = Array.isArray(vt) ? vt : JSON.parse(vt || '[]');
    return arr.map(v => v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())).join(' / ') || 'Economy';
  } catch { return 'Economy'; }
}

function _pcCompanyBlock() {
  return `<div style="margin-top:10px;font-size:11px;line-height:1.75;color:#1a1a2e">
    <div style="font-weight:700;font-size:11.5px">POWERCABS IRELAND LIMITED</div>
    <div>Address: Inchicore Dublin</div>
    <div>Telephone: 01 2030 727</div>
    <div>Email: Info@powercabs.ie</div>
    <div>Website: www.PowerCabs.ie</div>
    <div>VAT: (TAX 04301619NH)</div>
  </div>`;
}

function _pcFooterText() {
  return `<div style="margin-top:22px;font-size:10.5px;color:#555;line-height:1.8;border-top:1px solid #E2E8F0;padding-top:14px">
    <div>Any queries regarding Payment/Errors, please email the accounts
      <a href="mailto:accounts@powercabs.ie" style="color:#F37A20;text-decoration:none">accounts@powercabs.ie</a>
    </div>
    <div>All Payments will be made in Euros to the provided IBAN by drivers.</div>
    <div>Negative amounts owe to PCLI will be charge in next invoice or by IBAN.</div>
    <div style="font-weight:700;margin-top:5px">Thanks for your dedication and choosing PowerCabs Ireland Limited.</div>
  </div>`;
}

function _eur(n) { return '€' + n.toFixed(2); }

function _payoutTable(jobs, prepaid, cash, gross, commission, net, toll) {
  // Prepaid/card/wallet: PowerCabs collected the fare → PC owes driver their share
  // Cash: driver collected the fare → driver owes PC their commission
  const commPct            = INVOICE_COMMISSION_PCT / 100;
  const driverPct          = 100 - INVOICE_COMMISSION_PCT;
  const prepaidDriverShare = prepaid * (1 - commPct); // gross PC owes driver
  const cashCommOwed       = cash    * commPct;        // gross driver owes PC

  const netValue    = prepaidDriverShare + toll - cashCommOwed;
  const owedToDriver = Math.max(0, netValue);
  const owedToPC     = Math.max(0, -netValue);

  return `
  <div style="font-weight:700;font-size:12px;text-decoration:underline;margin-bottom:8px">Total Payout Breakdown</div>
  <table style="border-collapse:collapse;font-size:12px;min-width:340px">
    <tr><td style="padding:3px 12px 3px 0">Total Number of Jobs</td><td style="padding:3px 0;text-align:right;font-weight:600">${jobs.toString().padStart(2,'0')}</td></tr>
    <tr><td style="padding:3px 12px 3px 0">PrePaid / Card / Wallet Jobs Amount</td><td style="padding:3px 0;text-align:right">${_eur(prepaid)}</td></tr>
    <tr><td style="padding:3px 12px 3px 0">Cash Collected by Driver</td><td style="padding:3px 0;text-align:right">${_eur(cash)}</td></tr>
    <tr style="font-weight:700;border-top:2px solid #1a1a2e;border-bottom:1px solid #1a1a2e">
      <td style="padding:5px 12px 5px 0">Total Gross Pay</td><td style="padding:5px 0;text-align:right">${_eur(gross)}</td>
    </tr>
    <tr style="font-weight:700"><td style="padding:3px 12px 3px 0">PCLI Commission</td><td style="padding:3px 0;text-align:right">${INVOICE_COMMISSION_PCT}%</td></tr>
    <tr><td style="padding:3px 12px 3px 0">PCLI Revenue</td><td style="padding:3px 0;text-align:right">${_eur(commission)}</td></tr>
    <tr><td style="padding:3px 12px 3px 0">Driver's Net Earnings</td><td style="padding:3px 0;text-align:right">${_eur(net)}</td></tr>
    <tr><td style="padding:3px 12px 3px 0">Tolls</td><td style="padding:3px 0;text-align:right">${_eur(toll)}</td></tr>
    <tr><td style="padding:3px 12px 3px 0">Driver Peaktime Incentive</td><td style="padding:3px 0;text-align:right">€0.00</td></tr>
    <tr style="border-top:1px solid #ddd">
      <td style="padding:5px 12px 5px 0;color:#16a34a;font-weight:600">PowerCabs owes Driver (Prepaid ${driverPct}% share + Tolls)</td>
      <td style="padding:5px 0;text-align:right;color:#16a34a;font-weight:600">${_eur(prepaidDriverShare + toll)}</td>
    </tr>
    <tr>
      <td style="padding:5px 12px 5px 0;color:#dc2626;font-weight:600">Driver owes PowerCabs (Cash commission ${INVOICE_COMMISSION_PCT}%)</td>
      <td style="padding:5px 0;text-align:right;color:#dc2626;font-weight:600">${_eur(cashCommOwed)}</td>
    </tr>
    <tr style="font-weight:700;border-top:2px solid #1a1a2e;border-bottom:1px solid #1a1a2e">
      <td style="padding:5px 12px 5px 0">Net Owed to Driver</td>
      <td style="padding:5px 0;text-align:right">${owedToDriver > 0 ? `<strong style="color:#16a34a">${_eur(owedToDriver)}</strong>` : `<span style="color:#94a3b8">€0.00</span>`}</td>
    </tr>
    <tr style="font-weight:700">
      <td style="padding:4px 12px 4px 0">Net Owed to PowerCabs</td>
      <td style="padding:4px 0;text-align:right">${owedToPC > 0 ? `<strong style="color:#dc2626">${_eur(owedToPC)}</strong>` : `<span style="color:#94a3b8">€0.00</span>`}</td>
    </tr>
  </table>`;
}

function buildDriverInvoiceHtml(r, id) {
  const fareEur    = parseFloat(r.fare_eur)   || 0;
  const finalFare  = parseFloat(r.final_fare) || fareEur;  // meter fare — basis for all pay
  const toll       = parseFloat(r.toll)       || 0;
  const commission = finalFare * INVOICE_COMMISSION_PCT / 100;
  const net        = finalFare - commission;
  const invoiceNo  = 'PC-' + id.slice(0, 8).toUpperCase();

  const payMethod = r.payment_method || 'Cash';
  const isCard    = /card|wallet|stripe/i.test(payMethod);
  const prepaid   = isCard ? finalFare : 0;
  const cash      = isCard ? 0 : finalFare;

  const vehicleType = _vehicleLabel(r.vehicle_type);

  const rideRow = `
    <tr>
      <td style="padding:7px 8px;border:1px solid #ddd">${escHtml(fmtDate(r.created_at))}</td>
      <td style="padding:7px 8px;border:1px solid #ddd">${escHtml(payMethod)}</td>
      <td style="padding:7px 8px;border:1px solid #ddd">${escHtml(vehicleType)}</td>
      <td style="padding:7px 8px;border:1px solid #ddd">${escHtml(r.dest_addr || '—')}</td>
      <td style="padding:7px 8px;border:1px solid #ddd;text-align:right">€${fareEur.toFixed(2)}</td>
      <td style="padding:7px 8px;border:1px solid #ddd;text-align:right;font-weight:600">€${finalFare.toFixed(2)}</td>
    </tr>
    <tr>
      <td colspan="6" style="padding:3px 8px;font-size:10px;color:#888;border:1px solid #ddd;border-top:none">
        Ride ID: ${escHtml(id)} &nbsp;|&nbsp; Pickup: ${escHtml(r.pickup_addr || '—')}
      </td>
    </tr>`;

  return `
  <div id="invoicePrintArea" style="font-family:'Poppins',Arial,sans-serif;color:#1a1a2e;padding:32px;background:#fff">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #F37A20;padding-bottom:18px;margin-bottom:18px">
      <div>
        <img src="assets/img/logo.png" alt="PowerCabs" style="height:48px;object-fit:contain">
        ${_pcCompanyBlock()}
      </div>
      <div>
        <table style="font-size:11.5px;border-collapse:collapse">
          <tr><td style="padding:2px 20px 2px 0;color:#555">Document Number</td><td style="font-weight:700">${escHtml(invoiceNo)}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Date Issued</td><td style="font-weight:700">${escHtml(fmtDate(r.created_at))}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Cycle</td><td style="font-weight:700">Per Trip</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Driver's Name</td><td style="font-weight:700">${escHtml(r.driver_name || '—')}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Driver's Phone</td><td style="font-weight:700">${escHtml(r.driver_phone || '—')}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Driver's License</td><td style="font-weight:700">${escHtml(r.driver_license || '—')}</td></tr>
        </table>
      </div>
    </div>

    <div style="text-align:center;font-size:12.5px;font-weight:600;margin-bottom:16px">
      Your PowerCabs trip details for ${escHtml(fmtDate(r.created_at))}
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:11.5px;margin-bottom:22px">
      <thead>
        <tr style="background:#F8FAFC">
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Date/Time</th>
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Payment Method</th>
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Vehicle Type</th>
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Destination</th>
          <th style="text-align:right;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Est. Fare</th>
          <th style="text-align:right;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Meter Fare</th>
        </tr>
      </thead>
      <tbody>${rideRow}</tbody>
    </table>

    ${_payoutTable(1, prepaid, cash, finalFare, commission, net, toll)}
    ${_pcFooterText()}
  </div>`;
}

function buildDriverStatementHtml(data) {
  const period = (data.date_from || data.date_to)
    ? `${data.date_from || 'Start'} to ${data.date_to || 'Today'}`
    : 'All time';

  let totalGross = 0, totalCommission = 0, totalPrepaid = 0, totalCash = 0, totalToll = 0;
  const rowsHtml = data.rows.map(r => {
    const fareEur   = parseFloat(r.fare_eur)   || 0;
    const finalFare = parseFloat(r.final_fare) || fareEur;  // meter fare — basis for all pay
    const toll      = parseFloat(r.toll)       || 0;
    const isCard    = /card|wallet|stripe/i.test(r.payment_method || '');
    totalGross      += finalFare;
    totalCommission += finalFare * INVOICE_COMMISSION_PCT / 100;
    totalPrepaid    += isCard ? finalFare : 0;
    totalCash       += isCard ? 0 : finalFare;
    totalToll       += toll;
    return `
      <tr style="border-bottom:1px solid #E2E8F0">
        <td style="padding:6px 8px;font-size:11px">${escHtml(fmtDate(r.date))}</td>
        <td style="padding:6px 8px;font-size:11px">${escHtml(r.payment_method || 'Cash')}</td>
        <td style="padding:6px 8px;font-size:11px">${escHtml(_vehicleLabel(r.vehicle_type))}</td>
        <td style="padding:6px 8px;font-size:11px">${escHtml(r.dest || '—')}</td>
        <td style="padding:6px 8px;font-size:11px;text-align:right">€${fareEur.toFixed(2)}</td>
        <td style="padding:6px 8px;font-size:11px;text-align:right;font-weight:600">€${finalFare.toFixed(2)}</td>
      </tr>
      <tr>
        <td colspan="6" style="padding:2px 8px;font-size:10px;color:#888;border-bottom:1px solid #F0F2F5">
          Ride ID: ${escHtml(r.id.slice(0,8).toUpperCase())} &nbsp;|&nbsp; Pickup: ${escHtml(r.pickup || '—')}
        </td>
      </tr>`;
  }).join('');

  const totalNet  = totalGross - totalCommission;

  return `
  <div id="statementPrintArea" style="font-family:'Poppins',Arial,sans-serif;color:#1a1a2e;padding:32px;background:#fff;width:780px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #F37A20;padding-bottom:18px;margin-bottom:18px">
      <div>
        <img src="assets/img/logo.png" alt="PowerCabs" style="height:48px;object-fit:contain">
        ${_pcCompanyBlock()}
      </div>
      <div>
        <table style="font-size:11.5px;border-collapse:collapse">
          <tr><td style="padding:2px 20px 2px 0;color:#555">Document Number</td><td style="font-weight:700">PC-${new Date().toISOString().slice(0,10).replace(/-/g,'')}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Period</td><td style="font-weight:700">${escHtml(period)}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Cycle</td><td style="font-weight:700">Custom</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Driver's Name</td><td style="font-weight:700">${escHtml(data.driver_name || '—')}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Driver's License</td><td style="font-weight:700">${escHtml(data.driver_license || '—')}</td></tr>
          <tr><td style="padding:2px 20px 2px 0;color:#555">Generated</td><td style="font-weight:700">${escHtml(fmtDate(new Date().toISOString()))}</td></tr>
        </table>
      </div>
    </div>

    <div style="text-align:center;font-size:12.5px;font-weight:600;margin-bottom:16px">
      Your PowerCabs tour overview for ${escHtml(period)}
    </div>

    <table style="width:100%;border-collapse:collapse;margin-bottom:22px;font-size:11.5px">
      <thead>
        <tr style="background:#F8FAFC">
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Date/Time</th>
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Payment Method</th>
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Vehicle Type</th>
          <th style="text-align:left;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Destination</th>
          <th style="text-align:right;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Est. Fare</th>
          <th style="text-align:right;padding:7px 8px;border:1px solid #ddd;font-size:10px;text-transform:uppercase">Meter Fare</th>
        </tr>
      </thead>
      <tbody>${rowsHtml}</tbody>
    </table>

    ${_payoutTable(data.rows.length, totalPrepaid, totalCash, totalGross, totalCommission, totalNet, totalToll)}
    ${_pcFooterText()}
  </div>`;
}

/* ─── Invoice PDF download (lazy-loads html2canvas + jsPDF) ───────── */
let _pdfLibsLoaded  = false;
let _pdfLibsPending = [];
function loadPdfLibs(cb) {
  if (_pdfLibsLoaded) { cb(); return; }
  _pdfLibsPending.push(cb);
  if (_pdfLibsPending.length > 1) return; // already loading

  let loaded = 0;
  function onBothLoaded() {
    if (++loaded < 2) return;
    _pdfLibsLoaded = true;
    _pdfLibsPending.forEach(fn => fn());
    _pdfLibsPending = [];
  }
  function onError() { Toast.show('Failed to load PDF library.', 'error'); }

  const s1 = document.createElement('script');
  s1.src    = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
  s1.onload = onBothLoaded;
  s1.onerror = onError;

  const s2 = document.createElement('script');
  s2.src    = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
  s2.onload = onBothLoaded;
  s2.onerror = onError;

  document.head.appendChild(s1);
  document.head.appendChild(s2);
}

function downloadInvoicePdf() {
  const id = _currentInvoiceId;
  if (!id || !_invoiceData[id]) return;
  const btn = document.getElementById('invDownloadBtn');
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating…';

  loadPdfLibs(() => {
    const el = document.getElementById('invoicePrintArea');
    html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
      const { jsPDF } = window.jspdf;
      const pdf       = new jsPDF('p', 'pt', 'a4');
      const pageWidth  = pdf.internal.pageSize.getWidth();
      const imgWidth   = pageWidth;
      const imgHeight  = canvas.height * imgWidth / canvas.width;
      pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, imgWidth, imgHeight);
      pdf.save(`PowerCabs-Invoice-${id.slice(0, 8).toUpperCase()}.pdf`);
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }).catch(() => {
      Toast.show('Failed to generate PDF.', 'error');
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    });
  });
}

/* ─── Combined statement PDF for a filtered list of invoices ───────
   Fetches the full filtered (unpaginated) row set from the server as
   JSON, then renders it using the same visual template as the single
   ride invoice (buildInvoiceHtml), captured via html2canvas and split
   across as many A4 pages as needed. */
function downloadFilteredInvoicesPdf(btnId) {
  const btn = document.getElementById(btnId);
  const originalHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating…';

  const params = new URLSearchParams(window.location.search);
  params.set('export', 'json');

  fetch('?' + params.toString())
    .then(res => res.json())
    .then(data => {
      if (!data.success) throw new Error('bad response');
      if (!data.rows.length) {
        Toast.show('No invoices match the current filters.', 'info');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        return;
      }
      buildStatementPdf(data, () => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      });
    })
    .catch(() => {
      Toast.show('Failed to generate statement PDF.', 'error');
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    });
}

function buildStatementHtml(data) {
  const s      = data.stats;
  const period = (data.date_from || data.date_to)
    ? `${data.date_from || 'Start'} to ${data.date_to || 'Today'}`
    : 'All time';

  const rowsHtml = data.rows.map(r => `
    <tr style="border-bottom:1px solid #E2E8F0">
      <td style="padding:7px 8px;color:#64748B;font-size:11px">${escHtml(r.id.slice(0, 8).toUpperCase())}</td>
      <td style="padding:7px 8px;font-size:11px">${escHtml(fmtDate(r.date))}</td>
      <td style="padding:7px 8px;font-size:11px;font-weight:500">${escHtml(r.passenger)}</td>
      <td style="padding:7px 8px;font-size:11px;font-weight:500">${escHtml(r.driver)}</td>
      <td style="padding:7px 8px;font-size:11px;text-align:right;font-weight:600">€${r.fare.toFixed(2)}</td>
      <td style="padding:7px 8px;font-size:11px;text-align:right;color:#dc2626">€${r.commission.toFixed(2)}</td>
      <td style="padding:7px 8px;font-size:11px;text-align:right;color:#16a34a">€${r.earnings.toFixed(2)}</td>
    </tr>`).join('');

  return `
  <div id="statementPrintArea" style="font-family:'Poppins',Arial,sans-serif;color:#1a1a2e;padding:32px;background:#fff;width:760px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #F37A20;padding-bottom:18px;margin-bottom:22px">
      <div>
        <img src="assets/img/logo.png" alt="PowerCabs" style="height:48px;object-fit:contain">
        ${_pcCompanyBlock()}
      </div>
      <div style="text-align:right">
        <div style="font-size:16px;font-weight:700">INVOICE STATEMENT</div>
        <div style="font-size:12px;color:#64748B;margin-top:4px">Period: <b>${escHtml(period)}</b></div>
        ${data.driver_name ? `<div style="font-size:12px;color:#64748B">Driver: ${escHtml(data.driver_name)}</div>` : ''}
        <div style="font-size:11px;color:#94A3B8;margin-top:2px">Generated: ${escHtml(fmtDate(new Date().toISOString()))}</div>
      </div>
    </div>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px 18px;margin-bottom:22px;display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center">
      <div>
        <div style="font-size:18px;font-weight:700">${s.count}</div>
        <div style="font-size:10.5px;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;margin-top:3px">Invoices</div>
      </div>
      <div>
        <div style="font-size:18px;font-weight:700;color:#16a34a">€${s.total_revenue.toFixed(2)}</div>
        <div style="font-size:10.5px;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;margin-top:3px">Total Revenue</div>
      </div>
      <div>
        <div style="font-size:18px;font-weight:700;color:#dc2626">€${s.total_commission.toFixed(2)}</div>
        <div style="font-size:10.5px;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;margin-top:3px">Commission (${INVOICE_COMMISSION_PCT}%)</div>
      </div>
      <div>
        <div style="font-size:18px;font-weight:700;color:#7c3aed">€${s.total_payout.toFixed(2)}</div>
        <div style="font-size:10.5px;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;margin-top:3px">Driver Payouts</div>
      </div>
    </div>

    <div style="margin-bottom:22px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;border-bottom:1px solid #E2E8F0;padding-bottom:6px">Itemized Rides</div>
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:#F8FAFC">
            <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Ride ID</th>
            <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Date</th>
            <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Passenger</th>
            <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Driver</th>
            <th style="text-align:right;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Fare</th>
            <th style="text-align:right;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Commission</th>
            <th style="text-align:right;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">Earnings</th>
          </tr>
        </thead>
        <tbody>${rowsHtml}</tbody>
      </table>
    </div>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px 18px;margin-bottom:18px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px">Totals</div>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0">
        <span style="color:#64748B">Total Fare Charged</span><span style="font-weight:600">€${s.total_revenue.toFixed(2)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0">
        <span style="color:#64748B">PowerCabs Commission (${INVOICE_COMMISSION_PCT}%)</span><span style="font-weight:600;color:#dc2626">- €${s.total_commission.toFixed(2)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0">
        <span style="color:#64748B">Driver Net Earnings</span><span style="font-weight:600;color:#16a34a">€${s.total_payout.toFixed(2)}</span>
      </div>
      <div style="border-top:2px solid #1a1a2e;margin:10px 0 8px"></div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700">
        <span>Total Charged to Passengers</span><span style="color:#F37A20">€${s.total_revenue.toFixed(2)}</span>
      </div>
    </div>

    <div style="text-align:center;border-top:1px solid #E2E8F0;padding-top:14px;font-size:11px;color:#94A3B8">
      <div>This is a computer-generated statement and does not require a signature.</div>
      <div style="margin-top:3px">PowerCabs · powercabs.ie · support@powercabs.ie</div>
    </div>
  </div>`;
}

/* ─── Shared: render an off-screen HTML template to a (possibly
   multi-page) A4 PDF via html2canvas, slicing the tall canvas across
   as many pages as needed. Used by the statement and corporate
   invoice builders below. ─────────────────────────────────────── */
function renderHtmlToPdf(html, areaId, filename, done) {
  loadPdfLibs(() => {
    const container = document.createElement('div');
    container.style.position = 'fixed';
    container.style.left     = '-10000px';
    container.style.top      = '0';
    container.innerHTML = html;
    document.body.appendChild(container);

    const el = container.querySelector('#' + areaId);
    html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
      const { jsPDF }     = window.jspdf;
      const pdf           = new jsPDF('p', 'pt', 'a4');
      const pageWidth     = pdf.internal.pageSize.getWidth();
      const pageHeight    = pdf.internal.pageSize.getHeight();
      const imgWidth      = pageWidth;
      const pxPerPt       = canvas.width / imgWidth;
      const pageHeightPx  = pageHeight * pxPerPt;

      let renderedPx = 0;
      let firstPage  = true;
      while (renderedPx < canvas.height) {
        const sliceHeightPx = Math.min(pageHeightPx, canvas.height - renderedPx);
        const pageCanvas = document.createElement('canvas');
        pageCanvas.width  = canvas.width;
        pageCanvas.height = sliceHeightPx;
        pageCanvas.getContext('2d').drawImage(
          canvas, 0, renderedPx, canvas.width, sliceHeightPx, 0, 0, canvas.width, sliceHeightPx
        );
        if (!firstPage) pdf.addPage();
        pdf.addImage(pageCanvas.toDataURL('image/png'), 'PNG', 0, 0, imgWidth, sliceHeightPx / pxPerPt);
        renderedPx += sliceHeightPx;
        firstPage = false;
      }

      pdf.save(filename);
      document.body.removeChild(container);
      done && done();
    }).catch(() => {
      document.body.removeChild(container);
      Toast.show('Failed to generate PDF.', 'error');
      done && done();
    });
  });
}

function buildStatementPdf(data, done) {
  const driverSlug = data.driver_name ? data.driver_name.replace(/\s+/g, '-') : 'Statement';
  const filename   = `PowerCabs-${driverSlug}-${new Date().toISOString().slice(0, 10)}.pdf`;
  const html       = data.driver_mode ? buildDriverStatementHtml(data) : buildStatementHtml(data);
  renderHtmlToPdf(html, 'statementPrintArea', filename, done);
}

/* ─── Corporate account invoice (client-facing — no commission
   breakdown shown, just itemized rides + total due) ──────────────── */
function downloadCorporateInvoicePdf(cid, company) {
  const params = new URLSearchParams(window.location.search);
  params.set('export', 'json');
  params.set('cid', cid);

  Toast.show('Generating invoice for ' + company + '…', 'info');

  fetch('?' + params.toString())
    .then(res => res.json())
    .then(data => {
      if (!data.success) throw new Error('bad response');
      if (!data.rows.length) {
        Toast.show('No completed rides for this account in the selected period.', 'info');
        return;
      }
      const filename = `PowerCabs-Corporate-Invoice-${cid}-${new Date().toISOString().slice(0, 10)}.pdf`;
      renderHtmlToPdf(buildCorporateInvoiceHtml(data), 'corpInvoicePrintArea', filename);
    })
    .catch(() => Toast.show('Failed to generate invoice.', 'error'));
}

function buildCorporateInvoiceHtml(data) {
  const period = (data.date_from || data.date_to)
    ? `${data.date_from || 'Start'} to ${data.date_to || 'Today'}`
    : 'All time';

  const rowsHtml = data.rows.map(r => `
    <tr style="border-bottom:1px solid #E2E8F0">
      <td style="padding:7px 8px;font-size:11px">${escHtml(fmtDate(r.date))}</td>
      <td style="padding:7px 8px;font-size:11px;font-weight:500">${escHtml(r.employee)}</td>
      <td style="padding:7px 8px;font-size:11px">${escHtml(r.pickup)}</td>
      <td style="padding:7px 8px;font-size:11px">${escHtml(r.dest)}</td>
      <td style="padding:7px 8px;font-size:11px;text-align:right;font-weight:600">€${r.fare.toFixed(2)}</td>
    </tr>`).join('');

  return `
  <div id="corpInvoicePrintArea" style="font-family:'Poppins',Arial,sans-serif;color:#1a1a2e;padding:32px;background:#fff;width:760px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #F37A20;padding-bottom:18px;margin-bottom:22px">
      <div><img src="assets/img/logo.png" alt="PowerCabs" style="height:48px;object-fit:contain"></div>
      <div style="text-align:right">
        <div style="font-size:16px;font-weight:700">CORPORATE INVOICE</div>
        <div style="font-size:12px;color:#64748B;margin-top:4px">Period: <b>${escHtml(period)}</b></div>
        <div style="font-size:11px;color:#94A3B8;margin-top:2px">Generated: ${escHtml(fmtDate(new Date().toISOString()))}</div>
      </div>
    </div>

    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px 16px;margin-bottom:22px">
      <div style="font-size:11px;font-weight:700;color:#F37A20;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px">Billed To</div>
      <div style="font-size:13.5px;font-weight:600">${escHtml(data.company)}</div>
      ${data.address ? `<div style="font-size:12px;color:#64748B;margin-top:3px">${escHtml(data.address)}</div>` : ''}
      ${data.email ? `<div style="font-size:12px;color:#64748B">${escHtml(data.email)}</div>` : ''}
      <div style="font-size:11px;color:#94A3B8;margin-top:3px">Account ID: ${escHtml(data.cid)}</div>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-bottom:18px">
      <thead>
        <tr style="background:#F8FAFC">
          <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase">Date</th>
          <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase">Employee</th>
          <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase">Pickup</th>
          <th style="text-align:left;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase">Destination</th>
          <th style="text-align:right;padding:8px;border-bottom:2px solid #1a1a2e;color:#64748B;font-size:10px;text-transform:uppercase">Fare</th>
        </tr>
      </thead>
      <tbody>${rowsHtml}</tbody>
    </table>

    <div style="display:flex;justify-content:flex-end;margin-bottom:18px">
      <div style="width:220px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px 16px">
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700">
          <span>Total Due</span><span style="color:#F37A20">€${data.total.toFixed(2)}</span>
        </div>
      </div>
    </div>

    <div style="text-align:center;border-top:1px solid #E2E8F0;padding-top:14px;font-size:11px;color:#94A3B8">
      <div>Please remit payment per your agreed billing cycle with PowerCabs.</div>
      <div style="margin-top:3px">PowerCabs · powercabs.ie · support@powercabs.ie</div>
    </div>
  </div>`;
}
