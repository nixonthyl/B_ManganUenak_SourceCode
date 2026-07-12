<?php 
include 'koneksi.php';
$id = $_GET['id'];
$data = $koneksi->query("SELECT * FROM menu WHERE id_menu = $id")->fetch_assoc();
$nama_lama = $data['nama_menu'];
$jenis_lama = $data['jenis'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-5">
<div class="container">
    <div class="card shadow mx-auto" style="max-width: 600px;">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Edit Menu Restoran</h4>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Nama Menu</label>
                    <input type="text" name="nama_menu" value="<?= $data['nama_menu'] ?>" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jenis/Kategori</label>
                    <select name="jenis" class="form-select">
                        <option value="makanan" <?= ($data['jenis'] == 'makanan') ? 'selected' : '' ?>>Makanan</option>
                        <option value="minuman" <?= ($data['jenis'] == 'minuman') ? 'selected' : '' ?>>Minuman</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Harga Porsi</label>
                    <input type="number" name="harga" value="<?= $data['harga_porsi'] ?>" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ganti Gambar (Opsional)</label>
                    <input type="file" name="foto" class="form-control" accept="image/*">
                </div>
                <div class="mt-4 d-flex justify-content-between">
                    <button type="submit" name="update" class="btn btn-success px-4">Simpan Perubahan</button>
                    <a href="manage_menu.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if (isset($_POST['update'])) {
    $nama_baru = mysqli_real_escape_string($koneksi, $_POST['nama_menu']);
    $jenis_baru = $_POST['jenis'];
    $harga_baru = $_POST['harga'];

    // Tambahkan SEBELUM query UPDATE dijalankan
    if ($harga_baru < 1000 || $harga_baru > 10000000) {
        echo "<script>alert('Harga harus antara Rp1.000 dan Rp10.000.000!');
        location='manage_menu.php';</script>";
        exit;
    }

    // 1. Update Database
    $koneksi->query("UPDATE menu SET nama_menu='$nama_baru', jenis='$jenis_baru', harga_porsi='$harga_baru' WHERE id_menu=$id");

    // 2. Logika Sinkronisasi File Gambar
    $folder = (strtolower($jenis_baru) == 'minuman') ? 'minuman' : 'makanan';
    $file_lama = "img/$folder/" . strtolower($nama_lama) . ".jpg";
    $file_baru = "img/$folder/" . strtolower($nama_baru) . ".jpg";

    // Jika nama menu berubah, ubah juga nama filenya agar tidak "patah" link-nya
    if (strtolower($nama_lama) != strtolower($nama_baru)) {
        if (file_exists($file_lama)) {
            rename($file_lama, $file_baru);
        }
    }

    // Jika user mengupload file baru, timpa file yang ada
    if ($_FILES['foto']['name'] != "") {
        move_uploaded_file($_FILES['foto']['tmp_name'], $file_baru);
    }

    echo "<script>alert('Data berhasil diperbarui!'); location='manage_menu.php';</script>";
}
?>
</body>
</html>