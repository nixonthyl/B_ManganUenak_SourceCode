<?php
include 'koneksi.php';
session_start();

// Proses Tambah Menu ke Database
if (isset($_POST['simpan'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_menu']);
    $harga = $_POST['harga_porsi'];
    $jenis = $_POST['jenis'];

    // Tambahkan SEBELUM query INSERT dijalankan
    if ($harga < 1000 || $harga > 10000000) {
        echo "<script>alert('Harga harus antara Rp1.000 dan Rp10.000.000!');
        window.location='manage_menu.php';</script>";
        exit;
    }

    // Query simpan ke database (Tanpa kolom foto sesuai strukturmu)
    $query = "INSERT INTO menu (nama_menu, harga_porsi, jenis) VALUES ('$nama', '$harga', '$jenis')";
    
    if (mysqli_query($koneksi, $query)) {
        // Logika Upload Foto: Nama file disamakan dengan input nama_menu (lowercase)
        if ($_FILES['foto']['name'] != "") {
            $tmp = $_FILES['foto']['tmp_name'];
            $nama_file = strtolower($nama) . ".jpg";
            
            // Tentukan folder berdasarkan jenis
            $folder = (strtolower($jenis) == 'minuman') ? 'minuman' : 'makanan';
            $path = "img/" . $folder . "/" . $nama_file;
            
            move_uploaded_file($tmp, $path);
        }
        echo "<script>alert('Menu Berhasil Ditambahkan!'); window.location='manage_menu.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kelola Menu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        
        /* Form Tambah Menu (Di Atas) */
        .form-container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 40px; }
        .form-container h2 { margin-top: 0; color: #006699; border-bottom: 2px solid #006699; padding-bottom: 10px; }
        .form-grid { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
        .form-grid input, .form-grid select { padding: 12px; border: 1px solid #ccc; border-radius: 6px; flex: 1; min-width: 200px; }
        .btn-simpan { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-simpan:hover { background: #218838; }

        /* Grid Kartu Menu */
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 25px; }
        .card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 6px 15px rgba(0,0,0,0.1); transition: 0.3s; text-align: center; }
        .card:hover { transform: translateY(-8px); }
        .card img { width: 100%; height: 180px; object-fit: cover; background-color: #eee; }
        .card-info { padding: 15px; }
        .card-title { font-size: 1.2rem; font-weight: bold; color: #333; margin: 10px 0; }
        .card-price { color: #e67e22; font-size: 1.1rem; font-weight: bold; margin-bottom: 15px; }

        /* Tombol Edit & Hapus */
        .card-actions { display: flex; gap: 10px; justify-content: center; padding: 0 15px 20px; }
        .btn-edit { background: #ffc107; color: #000; text-decoration: none; padding: 8px 20px; border-radius: 6px; font-size: 14px; font-weight: bold; flex: 1; }
        .btn-delete { background: #dc3545; color: #fff; text-decoration: none; padding: 8px 20px; border-radius: 6px; font-size: 14px; font-weight: bold; flex: 1; }
        .btn-back { display: inline-block; margin-bottom: 20px; color: #006699; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="halaman_admin.php" class="btn-back">← Kembali ke Dashboard</a>
    
    <div class="form-container">
        <h2>TAMBAH MENU BARU</h2>
        <form action="" method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="text" name="nama_menu" placeholder="Nama Makanan/Minuman" required>
            <input type="number" name="harga_porsi" placeholder="Harga (Contoh: 15000)" required>
            <select name="jenis" required>
                <option value="">Pilih Kategori</option>
                <option value="makanan">Makanan</option>
                <option value="minuman">Minuman</option>
            </select>
            <input type="file" name="foto" accept="image/*" required>
            <button type="submit" name="simpan" class="btn-simpan">TAMBAH MENU</button>
        </form>
    </div>

    <h2 style="color: #333; margin-bottom: 20px;">DAFTAR MENU RESTORAN</h2>
    
    <div class="menu-grid">
        <?php
        $result = $koneksi->query("SELECT * FROM menu");
        while ($row = $result->fetch_assoc()) {
            // Logika Nama File Foto: nama_menu kecil semua + .jpg
            $nama_file = strtolower($row['nama_menu']) . ".jpg";
            $folder = (strtolower($row['jenis']) == 'minuman') ? 'minuman' : 'makanan';
            $path_foto = "img/" . $folder . "/" . $nama_file;

            // Cek jika file tidak ada di folder
            if (!file_exists($path_foto)) { $path_foto = "img/logo.png"; }
        ?>
        <div class="card">
            <img src="<?php echo $path_foto; ?>" alt="Foto <?php echo $row['nama_menu']; ?>">
            <div class="card-info">
                <div class="card-title"><?php echo $row['nama_menu']; ?></div>
                <div class="card-price">Rp <?php echo number_format($row['harga_porsi'], 0, ',', '.'); ?></div>
            </div>
            <div class="card-actions">
                <a href="update_menu.php?id=<?php echo $row['id_menu']; ?>" class="btn-edit">EDIT</a>
                <a href="hapus_menu.php?id=<?php echo $row['id_menu']; ?>" class="btn-delete" onclick="return confirm('Hapus menu <?php echo $row['nama_menu']; ?>?')">HAPUS</a>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

</body>
</html>