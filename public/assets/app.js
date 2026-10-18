document.addEventListener('DOMContentLoaded', () => {
  const archiveModal = document.querySelector('#modalHapusBarang');
  if (archiveModal) {
    archiveModal.addEventListener('show.bs.modal', (event) => {
      const trigger = event.relatedTarget;
      if (!trigger) return;
      archiveModal.querySelector('[data-archive-item-id]').value = trigger.dataset.archiveId ?? '';
      archiveModal.querySelector('[data-archive-item-name]').textContent = trigger.dataset.archiveName ?? '';
      archiveModal.querySelector('[data-archive-item-stock]').textContent = trigger.dataset.archiveStock ?? '';
    });
    archiveModal.addEventListener('hidden.bs.modal', () => archiveModal.querySelector('form')?.reset());
  }
  document.querySelectorAll('#modalBarangMasuk, #modalBarangKeluar').forEach((modal) => {
    modal.addEventListener('hidden.bs.modal', () => modal.querySelector('form')?.reset());
  });
  const sectionLinks = [...document.querySelectorAll('.section-nav .nav-link[href^="#"]')];
  const setActiveLink = (link) => {
    sectionLinks.forEach((item) => {
      const active = item === link;
      item.classList.toggle('active', active);
      if (active) item.setAttribute('aria-current', 'location');
      else item.removeAttribute('aria-current');
    });
  };
  const initialLink = sectionLinks.find((link) => link.getAttribute('href') === window.location.hash)
    ?? sectionLinks[0];
  if (initialLink) setActiveLink(initialLink);
  sectionLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      const target = document.querySelector(link.getAttribute('href'));
      if (!target) return;
      event.preventDefault();
      setActiveLink(link);
      history.replaceState(null, '', link.getAttribute('href'));
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      const collapse = link.closest('.navbar-collapse.show');
      if (collapse && window.bootstrap?.Collapse) {
        window.bootstrap.Collapse.getOrCreateInstance(collapse).hide();
      }
    });
  });
  if ('IntersectionObserver' in window && sectionLinks.length) {
    const sections = sectionLinks
      .map((link) => ({ link, section: document.querySelector(link.getAttribute('href')) }))
      .filter((item) => item.section);
    const observer = new IntersectionObserver((entries) => {
      const visible = entries.filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (visible) {
        const match = sections.find((item) => item.section === visible.target);
        if (match) setActiveLink(match.link);
      }
    }, { rootMargin: '-12% 0px -65% 0px', threshold: [0, 0.1, 0.4] });
    sections.forEach((item) => observer.observe(item.section));
  }
  const nativeNavLinks = [...document.querySelectorAll('.dashboard-navbar .navbar-nav:not(.section-nav) .nav-link[href^="#"]')];
  const markNativeLink = (selected) => {
    nativeNavLinks.forEach((link) => {
      const active = link === selected;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };
  const markNativeHash = () => markNativeLink(nativeNavLinks.find((link) =>
    link.getAttribute('href') === window.location.hash) ?? nativeNavLinks[0]);
  if (nativeNavLinks.length) {
    markNativeHash();
    window.addEventListener('hashchange', markNativeHash);
    nativeNavLinks.forEach((link) => link.addEventListener('click', () => markNativeLink(link)));
  }
  const categoryButtons = [...document.querySelectorAll('[data-category-filter]')];
  const catalogCards = [...document.querySelectorAll('[data-catalog-card]')];
  const filterEmpty = document.querySelector('[data-filter-empty]');
  categoryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const category = button.dataset.categoryFilter;
      let visibleCount = 0;
      categoryButtons.forEach((item) => {
        const active = item === button;
        item.classList.toggle('btn-primary', active);
        item.classList.toggle('btn-outline-primary', !active);
        item.setAttribute('aria-pressed', String(active));
      });
      catalogCards.forEach((card) => {
        const visible = category === 'all' || card.dataset.category === category;
        card.classList.toggle('d-none', !visible);
        if (visible) visibleCount++;
      });
      if (filterEmpty) filterEmpty.classList.toggle('d-none', visibleCount > 0);
    });
  });
  document.querySelectorAll('.table-sort-button').forEach((button) => {
    button.addEventListener('click', () => {
      const table = button.closest('table');
      const tbody = table?.tBodies[0];
      const header = button.closest('th');
      if (!table || !tbody || !header) return;

      const columnIndex = [...header.parentElement.children].indexOf(header);
      const sameColumn = table.dataset.sortColumn === String(columnIndex);
      const direction = sameColumn && table.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
      const rows = [...tbody.rows].filter((row) =>
        row.cells[columnIndex] && !row.cells[0]?.hasAttribute('colspan'));
      if (!rows.length) return;

      const readValue = (row) => row.cells[columnIndex].dataset.sortValue
        ?? row.cells[columnIndex].textContent.trim();
      rows.sort((left, right) => {
        const leftValue = readValue(left);
        const rightValue = readValue(right);
        let comparison;
        if (button.dataset.sortType === 'number') {
          comparison = Number(leftValue) - Number(rightValue);
        } else if (button.dataset.sortType === 'date') {
          comparison = Date.parse(leftValue) - Date.parse(rightValue);
        } else {
          comparison = leftValue.localeCompare(rightValue, 'id', {
            numeric: true,
            sensitivity: 'base',
          });
        }
        return direction === 'asc' ? comparison : -comparison;
      });
      rows.forEach((row) => tbody.appendChild(row));
      table.dataset.sortColumn = String(columnIndex);
      table.dataset.sortDirection = direction;

      table.querySelectorAll('thead th').forEach((cell) => {
        cell.setAttribute('aria-sort', 'none');
        const indicator = cell.querySelector('.table-sort-indicator');
        if (indicator) indicator.textContent = '↕';
      });
      header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
      const indicator = button.querySelector('.table-sort-indicator');
      if (indicator) indicator.textContent = direction === 'asc' ? '↑' : '↓';
    });
  });
  document.querySelectorAll('form[data-request-category]').forEach((form) => {
    const category = form.dataset.requestCategory;
    form.querySelectorAll('[data-category-fields]').forEach((fields) => {
      const visible = fields.dataset.categoryFields === 'barang_siap'
        ? category === 'barang_siap'
        : category !== 'barang_siap';
      fields.hidden = !visible;
      fields.querySelectorAll('input').forEach((input) => { input.required = visible; });
    });
  });
  document.querySelectorAll('[data-outgoing-purpose]').forEach((select) => {
    const projectFields = select.form?.querySelector('[data-outgoing-project-fields]');
    if (!projectFields) return;
    const submitLabel = select.form?.querySelector('[data-outgoing-submit-label]');
    const updateProjectFields = () => {
      const visible = select.value === 'proyek';
      projectFields.hidden = !visible;
      projectFields.querySelectorAll('input').forEach((input) => { input.required = visible; });
      if (submitLabel) submitLabel.textContent = visible ? 'Ajukan ACC barang keluar' : 'Simpan barang keluar';
    };
    select.addEventListener('change', updateProjectFields);
    select.form?.addEventListener('reset', () => setTimeout(updateProjectFields, 0));
    updateProjectFields();
  });
  document.querySelectorAll('[data-unit-source]').forEach((select) => {
    const unitInput = document.getElementById(select.dataset.unitTarget);
    if (!unitInput) return;
    const updateUnit = () => {
      unitInput.value = select.selectedOptions[0]?.dataset.satuan ?? '';
    };
    select.addEventListener('change', updateUnit);
    select.form?.addEventListener('reset', () => setTimeout(updateUnit, 0));
    updateUnit();
  });
  document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
    form.querySelectorAll('select').forEach((select) => {
      select.addEventListener('change', () => form.requestSubmit());
    });
  });
  const stockSearch = document.querySelector('[data-stock-search]');
  const stockTable = document.querySelector('#stockTable');
  if (stockSearch && stockTable) {
    const stockRows = [...stockTable.querySelectorAll('[data-stock-row]')];
    const stockCount = document.querySelector('#stockTableCount');
    const emptyState = stockTable.querySelector('[data-stock-filter-empty]');
    const clearSearch = document.querySelector('[data-stock-search-clear]');
    const updateStockFilter = () => {
      const query = stockSearch.value.trim().toLocaleLowerCase('id');
      let visibleCount = 0;
      stockRows.forEach((row) => {
        const visible = !query || row.textContent.toLocaleLowerCase('id').includes(query);
        row.classList.toggle('d-none', !visible);
        if (visible) visibleCount++;
      });
      if (emptyState) emptyState.classList.toggle('d-none', !query || visibleCount > 0);
      if (stockCount) stockCount.textContent = query
        ? visibleCount + ' dari ' + stockRows.length + ' baris stok'
        : stockRows.length + ' baris stok';
    };
    stockSearch.addEventListener('input', updateStockFilter);
    clearSearch?.addEventListener('click', () => {
      stockSearch.value = '';
      updateStockFilter();
      stockSearch.focus();
    });
    updateStockFilter();
  }
  const purchasingWarehouseFilter = document.querySelector('[data-warehouse-filter]');
  const purchasingWarehouseSearch = document.querySelector('[data-warehouse-search]');
  const purchasingStockTable = document.querySelector('#purchasingStockTable');
  if (purchasingWarehouseFilter && purchasingWarehouseSearch && purchasingStockTable) {
    const rows = [...purchasingStockTable.querySelectorAll('[data-warehouse-row]')];
    const summaryRows = [...document.querySelectorAll('[data-location-stock-row]')];
    const summaryEmpty = document.querySelector('[data-location-stock-empty]');
    const updatePurchasingStock = () => {
      const warehouse = purchasingWarehouseFilter.value;
      const query = purchasingWarehouseSearch.value.trim().toLocaleLowerCase('id');
      rows.forEach((row) => {
        const matchesWarehouse = !warehouse || row.dataset.warehouseName === warehouse;
        const matchesSearch = !query || row.textContent.toLocaleLowerCase('id').includes(query);
        row.classList.toggle('d-none', !(matchesWarehouse && matchesSearch));
      });
      let visibleSummaryCount = 0;
      summaryRows.forEach((row) => {
        const visible = !warehouse || row.dataset.warehouseName === warehouse;
        row.classList.toggle('d-none', !visible);
        if (visible) visibleSummaryCount++;
      });
      summaryEmpty?.classList.toggle('d-none', !warehouse || visibleSummaryCount > 0);
    };
    purchasingWarehouseFilter.addEventListener('change', updatePurchasingStock);
    purchasingWarehouseSearch.addEventListener('input', updatePurchasingStock);
    updatePurchasingStock();
  }
  document.querySelectorAll('[data-confirm]').forEach((button) => {
    button.addEventListener('click', (event) => {
      if (!window.confirm(button.dataset.confirm)) event.preventDefault();
    });
  });
  document.querySelectorAll('input[type="number"]').forEach((input) => {
    input.addEventListener('change', () => {
      if (input.min && Number(input.value) < Number(input.min)) input.value = input.min;
      if (input.max && Number(input.value) > Number(input.max)) input.value = input.max;
    });
  });
});
