<p style="text-align: right;">{{ $job_details['employer']['city'] }}, {{ $current_date }}</p>

<p>
    Kepada Yth:<br>
    HRD {{ $job_details['employer']['name'] }}<br>
    {{ $job_details['employer']['address'] }}
</p>

<p></p>

<p>
    Dengan hormat,
</p>

<p>
    Sehubungan dengan adanya informasi lowongan pekerjaan di {{ $job_details['employer']['name'] }} sebagai {{ $job_details['position'] }}, maka bersama dengan surat ini saya:
</p>

<table style="vertical-align: top; width: 100%;">
    @foreach ($user_data as $user_data_name => $user_data_value)
        <tr>
            <td style="width: 35%;">{{ $user_data_name }}</td>
            <td style="width: 2%;"> : </td>
            <td>{{ $user_data_value }}</td>
        </tr>
    @endforeach
</table>

<p>
    Mengajukan lamaran pekerjaan sebagai karyawan di perusahaan yang Bapak/Ibu pimpin. Sebagai bahan pertimbangan, bersama ini saya lampirkan:
</p>

<ol>
    @foreach ($documents as $document)
        <li>{{ $document }}</li>
    @endforeach
</ol>

<p>
    Demikian surat lamaran kerja ini saya buat berdasarkan kondisi saya yang sebenarnya. Atas perhatian Bapak/Ibu saya sampaikan terima kasih.
</p>

<div style="width: 25%; float: right; text-align: center;">
    <p>Hormat saya,</p>
    <p></p>
    <p>{{ $user_data['Nama'] }}</p>
</div>