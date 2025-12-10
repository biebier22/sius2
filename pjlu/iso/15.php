<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Ruang dan Panitia Ujian</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        .header, .footer {
            text-align: center;
            font-weight: bold;
        }
        .table-container {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        table {
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #000;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer-details {
            text-align: left;
            margin-top: 30px;
            font-size: 14px;
        }
        .footer-details div {
            margin-bottom: 5px;
        }
        .total {
            font-weight: bold;
            text-align: left;
            padding-left: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <p>REKAPITULASI RUANG DAN PANITIA UJIAN PER TEMPAT UJIAN UTM</p>
        <p>28 April 2025</p>
    </div>
    
    <div class="header">
        <p>UT Daerah: ____________________</p>
        <p>Tempat Ujian: ____________________</p>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Lokasi Ujian</th>
                    <th>Hari Ujian</th>
                    <th>Jumlah Ruang Ujian</th>
                    <th colspan="3">Jumlah</th>
                    <th>Petugas Administrasi</th>
                </tr>
                <tr>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th>PJLU</th>
                    <th>Pengawas Keliling</th>
                    <th>Pengawas Ruang</th>
                    <th></th>
                </tr>
                <tr>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th>UT Daerah</th>
                    <th>UT Daerah</th>
                    <th>UT Daerah</th>
                    <th>UT Daerah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Lokasi 1</td>
                    <td>Senin</td>
                    <td>3</td>
                    <td>5</td>
                    <td>2</td>
                    <td>4</td>
                    <td>2</td>
                </tr>
                <tr>
                    <td>Lokasi 2</td>
                    <td>Selasa</td>
                    <td>2</td>
                    <td>4</td>
                    <td>3</td>
                    <td>3</td>
                    <td>1</td>
                </tr>
                <tr>
                    <td>Lokasi 3</td>
                    <td>Rabu</td>
                    <td>4</td>
                    <td>6</td>
                    <td>3</td>
                    <td>5</td>
                    <td>3</td>
                </tr>
                <!-- More rows as needed -->
            </tbody>
        </table>
    </div>

    <div class="total">
        <p>Total</p>
    </div>

    <div class="footer-details">
        <div>Tanggal: ________________</div>
        <div>Penanggung Jawab Tempat Ujian</div>
        <div>Nama: ____________________</div>
        <div>NIP: ____________________</div>
    </div>

    <div class="footer">
        <p>Keterangan: INS = Instansi lain</p>
    </div>
</div>

</body>
</html>
