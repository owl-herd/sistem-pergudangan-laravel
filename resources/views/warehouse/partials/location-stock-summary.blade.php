@if($warehouseStockSummary)
  <?php $maxWarehouseUnits = max(1, ...array_map(static fn ($warehouse): int => (int) $warehouse['unit'], $warehouseStockSummary)); ?>
  <section class="section-anchor location-stock-summary app-card card shadow-sm mb-4" id="stok-per-lokasi" aria-labelledby="locationStockHeading">
    <div class="card-body p-3 p-lg-4">
      <span class="section-kicker">Ikhtisar multi-gudang</span>
      <h2 class="h4 fw-bold text-primary-emphasis mt-1 mb-1" id="locationStockHeading">Stok per lokasi</h2>
      <p class="small text-secondary mb-3">Jumlah unit tersedia di tiap gudang.</p>
      <div class="location-stock-bars">
        @foreach($warehouseStockSummary as $warehouseSummary)
          <?php $warehouseUnits = (int) $warehouseSummary['unit']; $warehouseLow = (int) $warehouseSummary['rendah']; ?>
          <div class="location-stock-row" data-location-stock-row data-warehouse-name="{{ $warehouseSummary['nama'] }}">
            <div class="d-flex justify-content-between align-items-baseline gap-3 mb-2">
              <span class="location-stock-name">{{ $warehouseSummary['nama'] }}</span>
              <strong class="location-stock-total">{{ number_format($warehouseUnits) }}</strong>
            </div>
            <div class="progress location-stock-progress" role="img" aria-label="{{ $warehouseSummary['nama'] }}: {{ number_format($warehouseUnits) }} unit tersedia">
              <div class="progress-bar {{ $warehouseLow > 0 ? 'location-stock-progress-low' : '' }}" style="width: {{ min(100, ($warehouseUnits / $maxWarehouseUnits) * 100) }}%"></div>
            </div>
            <small class="location-stock-status {{ $warehouseLow > 0 ? 'text-warning-emphasis' : 'text-success-emphasis' }}">
              {{ $warehouseLow > 0 ? number_format($warehouseLow).' barang perlu perhatian' : 'Kondisi normal' }}
            </small>
          </div>
        @endforeach
      </div>
      <p class="small text-secondary mb-0 d-none" data-location-stock-empty>Tidak ada ringkasan untuk gudang ini.</p>
      <a class="location-stock-link" href="#stok">Lihat rincian stok <span aria-hidden="true">→</span></a>
    </div>
  </section>
@endif
