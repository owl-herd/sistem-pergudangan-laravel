@if($role === 'manager')
<section class="section-anchor app-card card shadow-sm mb-4" id="laporan"><div class="card-body p-3 p-lg-4">
<h2 class="h4 fw-bold text-primary-emphasis">Pilihan laporan</h2><p class="text-secondary">Pilih jenis laporan dan periode. Setiap jenis dapat dibuka atau diunduh secara terpisah. Stok menampilkan kondisi saat ini.</p>
<form action="laporan_pdf.php" method="get" class="row g-3 align-items-end">
<div class="col-md-4"><label class="form-label" for="jenis_laporan">Jenis laporan</label><select class="form-select" name="jenis_laporan" id="jenis_laporan">@foreach($reportTypes as $value => $label)<option value="{{ $value }}" @selected($value === 'stok')>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label" for="laporan_mulai">Dari tanggal</label><input class="form-control" type="date" id="laporan_mulai" name="tanggal_mulai" value="{{ $start }}" required></div>
<div class="col-md-3"><label class="form-label" for="laporan_akhir">Sampai tanggal</label><input class="form-control" type="date" id="laporan_akhir" name="tanggal_akhir" value="{{ $end }}" required></div>
<div class="col-md-2"><button class="btn btn-primary w-100" type="submit" name="tampilan" value="lihat" formtarget="_blank">Lihat laporan</button><button class="btn btn-outline-primary w-100 mt-2" type="submit">Unduh PDF</button></div>
</form></div></section>
@endif
