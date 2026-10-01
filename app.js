function openProductModal() {
  document.getElementById('productModal')?.classList.add('show');
}

function closeProductModal() {
  const modal = document.getElementById('productModal');
  if (!modal) return;
  modal.classList.remove('show');
  if (new URLSearchParams(location.search).has('edit')) location.href = '?page=products';
}

function showNotice(message, type = 'error') {
  const feedback = document.getElementById('formFeedback');
  if (feedback) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'error' ? 'danger' : 'success'}`;
    alert.setAttribute('role', type === 'error' ? 'alert' : 'status');
    alert.textContent = message;
    feedback.replaceChildren(alert);
    return;
  }
  const content = document.querySelector('.content');
  if (!content) return;
  content.querySelectorAll('.notice').forEach((notice) => notice.remove());
  const notice = document.createElement('div');
  notice.className = `notice ${type}`;
  notice.setAttribute('role', type === 'error' ? 'alert' : 'status');
  notice.textContent = message;
  content.prepend(notice);
  window.setTimeout(() => notice.classList.add('fade'), 5000);
}

function csrfToken() {
  return document.querySelector('#productForm [name="_csrf"]')?.value || '';
}

function productApiUrl() {
  return new URL('api/products.php', new URL('.', location.href));
}

function rememberSuccess(message) {
  sessionStorage.setItem('productActionSuccess', message);
  location.href = '?page=products';
}

function makeCell(tag, className, text) {
  const cell = document.createElement(tag);
  if (className) cell.className = className;
  if (text !== undefined) cell.textContent = text;
  return cell;
}

function renderProducts(products) {
  const body = document.querySelector('[data-products-api]');
  if (!body) return;
  body.replaceChildren();
  products.forEach((product) => {
    const row = document.createElement('tr');
    const id = Number(product.id);

    const idCell = makeCell('td');
    idCell.append(makeCell('span', 'record-id', `#${id}`));
    row.append(idCell);

    const nameCell = makeCell('td');
    const nameWrap = makeCell('div', 'record-name');
    nameWrap.append(makeCell('span', `record-avatar hue-${id % 5}`, (product.product_name || '?').slice(0, 1)));
    nameWrap.append(makeCell('strong', '', product.product_name));
    nameCell.append(nameWrap);
    row.append(nameCell);

    row.append(makeCell('td', '', product.unit || '—'));
    row.append(makeCell('td', '', product.category_name || '—'));
    const priceCell = makeCell('td');
    const price = Number(product.price);
    priceCell.append(makeCell('span', 'price', `$${Number.isFinite(price) ? price.toFixed(2) : '0.00'}`));
    row.append(priceCell);

    const actionCell = makeCell('td');
    const actions = makeCell('div', 'row-actions');
    const edit = makeCell('a', '', '✎');
    edit.href = `?page=products&edit=${encodeURIComponent(id)}`;
    edit.title = 'แก้ไขสินค้า';
    const remove = makeCell('button', '', '⌫');
    remove.type = 'button';
    remove.className = 'delete-product';
    remove.dataset.productId = String(id);
    remove.title = 'ลบสินค้า';
    remove.setAttribute('aria-label', 'ลบสินค้า');
    actions.append(edit, remove);
    actionCell.append(actions);
    row.append(actionCell);
    body.append(row);
  });

  if (products.length === 0) {
    const row = document.createElement('tr');
    const cell = makeCell('td', 'empty', 'ไม่พบรายการที่ตรงกัน');
    cell.colSpan = 6;
    row.append(cell);
    body.append(row);
  }
  document.querySelectorAll('[data-result-count], [data-footer-count]').forEach((node) => {
    node.textContent = String(products.length);
  });
}

async function loadProducts(search = '') {
  const body = document.querySelector('[data-products-api]');
  if (!body) return;
  try {
    const url = productApiUrl();
    if (search.trim()) url.searchParams.set('q', search.trim());
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'โหลดรายการสินค้าไม่ได้');
    renderProducts(result.data || []);
  } catch (error) {
    showNotice(error.message || 'โหลดรายการสินค้าผ่านระบบ API ไม่ได้');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.notice').forEach((notice) => {
    window.setTimeout(() => notice.classList.add('fade'), 5000);
  });

  const savedSuccess = sessionStorage.getItem('productActionSuccess');
  if (savedSuccess) {
    sessionStorage.removeItem('productActionSuccess');
    showNotice(savedSuccess, 'success');
  }

  const productBody = document.querySelector('[data-products-api]');
  if (productBody) {
    const searchForm = document.querySelector('.search-form');
    const searchInput = searchForm?.querySelector('[name="q"]');
    loadProducts(searchInput?.value || '');
    searchForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      const value = searchInput?.value || '';
      const url = new URL(location.href);
      url.searchParams.set('page', 'products');
      if (value.trim()) url.searchParams.set('q', value.trim());
      else url.searchParams.delete('q');
      history.replaceState({}, '', url);
      loadProducts(value);
    });
  }

  const productForm = document.getElementById('productForm');
  const editingProductId = Number(productForm?.elements.product_id.value || 0);
  if (editingProductId > 0) {
    const url = productApiUrl();
    url.searchParams.set('id', String(editingProductId));
    fetch(url, { headers: { Accept: 'application/json' } })
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'โหลดข้อมูลสินค้านี้ไม่ได้');
        return result.data;
      })
      .then((product) => {
        if (!productForm) return;
        productForm.elements.product_name.value = product.product_name || '';
        productForm.elements.category_id.value = String(product.category_id || '');
        productForm.elements.supplier_id.value = String(product.supplier_id || '');
        productForm.elements.unit.value = product.unit || '';
        productForm.elements.price.value = product.price || '';
      })
      .catch((error) => showNotice(error.message || 'โหลดข้อมูลสินค้านี้ไม่ได้'));
  }

  productForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = document.getElementById('saveProductButton');
    const productId = Number(form.elements.product_id.value || 0);
    const payload = {
      product_name: form.elements.product_name.value,
      category_id: Number(form.elements.category_id.value),
      supplier_id: Number(form.elements.supplier_id.value),
      unit: form.elements.unit.value,
      price: form.elements.price.value,
    };
    button.disabled = true;
    const saveLabel = 'กำลังบันทึก…';
    button.textContent = saveLabel;
    try {
      const url = productApiUrl();
      if (productId) url.searchParams.set('id', String(productId));
      const response = await fetch(url, {
        method: productId ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrfToken() },
        body: JSON.stringify(payload),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'บันทึกสินค้าไม่ได้');
      rememberSuccess(result.message || 'บันทึกสินค้าแล้ว');
    } catch (error) {
      showNotice(error.message || 'บันทึกสินค้าไม่ได้');
      button.disabled = false;
      button.textContent = 'บันทึกสินค้า';
    }
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('.delete-product');
    if (!button) return;
    if (!window.confirm('ยืนยันการลบสินค้านี้หรือไม่?')) return;
    button.disabled = true;
    try {
      const url = productApiUrl();
      url.searchParams.set('id', button.dataset.productId);
      const response = await fetch(url, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken() },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'ลบสินค้าไม่ได้');
      rememberSuccess(result.message || 'ลบสินค้าแล้ว');
    } catch (error) {
      showNotice(error.message || 'ลบสินค้าไม่ได้');
      button.disabled = false;
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeProductModal();
  });
});
