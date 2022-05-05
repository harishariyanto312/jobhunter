<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\BotUser;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\ApplyJob;
use Mpdf\Mpdf;
use iio\libmergepdf\Merger;
use iio\libmergepdf\Pages;
use Ilovepdf\Ilovepdf;

class BotOperationController extends Controller
{
    private $chat_id;
    private $message;
    private $bot_user;

    public function index(Request $request)
    {

        $request_data = $request->getContent();
        Storage::put('req.txt', $request_data);

        $chat_id = $request->input('message.chat.id');

        $this->chat_id = $chat_id;
        $this->message = $request->input('message.text');

        $bot_user = BotUser::firstOrCreate([
            'chat_id' => $chat_id
        ]);
        $this->bot_user = $bot_user;

        if ($bot_user->is_authenticated || $bot_user->current_operation == 'authenticating') {
            
            $current_operation = $bot_user->current_operation;
            switch ($current_operation) {
                case 'authenticating':
                    $this->authenticating();
                    break;
                case 'edit_personal_data':
                    $this->savePersonalData();
                    break;
                case 'edit_documents_order':
                    $this->saveDocumentsOrder();
                    break;
                case 'upload_cv':
                    $this->saveCV($request->input('message.document.file_id'), $request->input('message.document.mime_type'), $request->input('message.caption'));
                    break;
                case 'delete_cv':
                    $this->deleteCV();
                    break;
                case 'upload_documents':
                    $this->saveDocuments($request->input('message.document.file_id'), $request->input('message.document.mime_type'), $request->input('message.caption'));
                    break;
                case 'apply_job':
                    $this->sendJobApplication();
                    break;
                case 'select_cv':
                    $this->applyJob();
                    break;
                default:
                    $this->commandsHandler();
                    break;
            }

        }
        else {
            $this->authenticate();
        }

    }

    private function commandsHandler()
    {
        switch ($this->message) {
            case '/edit_personal_data':
                $this->editPersonalData();
                break;
            case '/view_personal_data':
                $this->viewPersonalData();
                break;
            case '/edit_documents_order':
                $this->editDocumentsOrder();
                break;
            case '/view_documents_order':
                $this->viewDocumentsOrder();
                break;
            case '/upload_cv':
                $this->uploadCV();
                break;
            case '/delete_cv':
                $this->chooseCV();
                break;
            case '/upload_documents':
                $this->uploadDocuments();
                break;
            case '/apply_job':
                $this->selectCV();
                break;
            default:
                $this->sendMessage('Perintah tidak dimengerti');
                break;
        }
    }

    private function telegramOperation($method_name, $data)
    {

        $url = 'https://api.telegram.org/bot' . config('telegram.token') . '/' . $method_name;
        return Http::post($url, $data);

    }

    private function getFile($file_id)
    {
        $get_path = $this->telegramOperation('getFile', [
            'file_id' => $file_id
        ]);
        $path = $get_path['result']['file_path'];

        $url = 'https://api.telegram.org/file/bot' . config('telegram.token') . '/' . $path;
        return file_get_contents($url);
    }

    private function sendMessage($text = '')
    {
        $this->telegramOperation('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => $text
        ]);
    }

    private function authenticate()
    {
        $this->sendMessage('Masukkan password');

        $this->bot_user->current_operation = 'authenticating';
        $this->bot_user->save();
    }

    private function authenticating()
    {
        if ($this->message === config('telegram.password')) {
            $this->sendMessage('Berhasil!');

            $this->bot_user->current_operation = 'idle';
            $this->bot_user->is_authenticated = true;
            $this->bot_user->save();
        }
        else {
            $this->sendMessage('Password salah, coba lagi!');
        }
    }

    private function editPersonalData()
    {
        $this->bot_user->current_operation = 'edit_personal_data';
        $this->bot_user->save();

        $this->sendMessage('Edit data pribadi dengan format seperti berikut:');

        $text = 'Nama : <Nama Anda>;' . "\n" .
            'Tempat dan Tanggal Lahir : <TTL Anda>;' . "\n" .
            'Jenis Kelamin : Laki-laki;';
        $this->sendMessage($text);
    }

    private function savePersonalData()
    {
        $this->bot_user->current_operation = 'idle';

        $result = [];

        $personal_data = explode(';', $this->message);
        foreach ($personal_data as $personal_data_item) {
            $personal_data_item = explode(':', $personal_data_item);
            if (count($personal_data_item) == 2) {
                $result[ trim($personal_data_item[0]) ] = trim($personal_data_item[1]);
            }
        }

        $this->bot_user->personal_data = json_encode($result);
        $this->bot_user->save();

        $this->sendMessage('Berhasil disimpan');
    }

    private function viewPersonalData()
    {
        $personal_data = $this->bot_user->personal_data;
        $personal_data = json_decode($personal_data);

        $result = '';
        foreach ($personal_data as $personal_data_title => $personal_data_value) {
            $result .= $personal_data_title . ' : ' . $personal_data_value . "\n";
        }

        $this->sendMessage($result);
    }

    private function editDocumentsOrder()
    {
        $this->bot_user->current_operation = 'edit_documents_order';
        $this->bot_user->save();

        $this->sendMessage('Edit urutan dokumen dengan format seperti berikut:');
        $this->sendMessage('CV;Scan KTP;Scan Ijazah;');
    }

    private function saveDocumentsOrder()
    {
        $this->bot_user->current_operation = 'idle';

        $result = [];

        $documents_order = explode(';', $this->message);
        foreach ($documents_order as $document) {
            $document = trim($document);
            if (!empty($document)) {
                $result[] = $document;
            }
        }

        $this->bot_user->documents = json_encode($result);
        $this->bot_user->save();

        $this->sendMessage('Berhasil disimpan');
    }

    private function viewDocumentsOrder()
    {
        $documents = $this->bot_user->documents;
        $documents = json_decode($documents);

        $result = '';
        $i = 1;
        foreach ($documents as $document) {
            $result .= $i . '. ' . $document . "\n";
            $i++;
        }

        $this->sendMessage($result);
    }

    private function uploadCV()
    {
        $this->bot_user->current_operation = 'upload_cv';
        $this->bot_user->save();

        $this->sendMessage('Silakan kirimkan CV (PDF) dengan caption sebagai nama CV');
    }

    private function saveCV($file_id, $file_mime, $file_name = '')
    {
        if ($file_mime == 'application/pdf') {
            $this->bot_user->current_operation = 'idle';

            $current_time = time();
            $filename = 'cv/' . $current_time . '.pdf';
            Storage::put($filename, $this->getFile($file_id));

            $document = new Document([
                'name' => empty($file_name) ? $current_time : $file_name,
                'url' => $filename
            ]);

            $this->bot_user->documents()->save($document);
            $this->bot_user->save();
            $this->sendMessage('Berhasil disimpan');
        }
        else {
            $this->sendMessage('Format file harus PDF');
        }
    }

    private function chooseCV()
    {
        $cvs = $this->bot_user->documents()->get();

        if (count($cvs) == 0) {
            $this->sendMessage('Anda belum mengupload CV');
            return;
        }

        $this->bot_user->current_operation = 'delete_cv';
        $this->bot_user->save();

        $result = [];
        $i = 0;
        foreach ($cvs as $cv) {
            $result[$i][] = ['text' => $cv->name];
            if (count($result[$i]) >= 2) {
                $i++;
            }
        }

        $this->telegramOperation('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => 'Pilih CV untuk dihapus',
            'reply_markup' => [
                'keyboard' => $result,
                'resize_keyboard' => true
            ]
        ]);
    }

    private function deleteCV()
    {
        $this->bot_user->current_operation = 'idle';
        $this->bot_user->save();

        $document = Document::where('name', $this->message)->first();
        Storage::delete($document->url);
        $document->delete();

        $this->telegramOperation('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => 'Berhasil dihapus',
            'reply_markup' => [
                'remove_keyboard' => true
            ]
        ]);
    }

    private function uploadDocuments()
    {
        $this->bot_user->current_operation = 'upload_documents';
        $this->bot_user->save();

        $this->sendMessage('Silakan kirimkan dokumen pendukung (PDF)');
    }

    private function saveDocuments($file_id, $file_mime, $file_name = '')
    {
        if ($this->bot_user->documents_url) {
            Storage::delete($this->bot_user->documents_url);
        }

        if ($file_mime == 'application/pdf') {
            $this->bot_user->current_operation = 'idle';

            $current_time = time();
            $filename = 'documents/' . $current_time . '.pdf';
            Storage::put($filename, $this->getFile($file_id));

            $this->bot_user->documents_url = $filename;
            $this->bot_user->save();
            $this->sendMessage('Berhasil disimpan');
        }
        else {
            $this->sendMessage('Format file harus PDF');
        }
    }

    private function selectCV()
    {
        $cvs = $this->bot_user->documents()->get();

        if (count($cvs) == 0) {
            $this->sendMessage('Anda belum mengupload CV');
            return;
        }

        $this->bot_user->current_operation = 'select_cv';
        $this->bot_user->save();

        $result = [];
        $i = 0;
        foreach ($cvs as $cv) {
            $result[$i][] = ['text' => $cv->name];
            if (count($result[$i]) >= 2) {
                $i++;
            }
        }

        $this->telegramOperation('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => 'Pilih CV untuk dikirim',
            'reply_markup' => [
                'keyboard' => $result,
                'resize_keyboard' => true
            ]
        ]);
    }

    private function applyJob()
    {
        $this->bot_user->mem = $this->message;
        $this->bot_user->current_operation = 'apply_job';
        $this->bot_user->save();

        // $this->sendMessage('Silakan kirimkan detail dengan format seperti berikut');

        $this->telegramOperation('sendMessage', [
            'chat_id' => $this->chat_id,
            'text' => 'Silakan kirimkan detail dengan format seperti berikut',
            'reply_markup' => [
                'remove_keyboard' => true
            ]
        ]);
        $this->sendMessage('Nama PT; Alamat PT; Kota PT; Posisi yang dilamar; Subject; Email PT;');
    }

    private function sendJobApplication()
    {
        $this->bot_user->current_operation = 'idle';

        $message = $this->message;
        $message = explode(';', $message);

        if (count($message) >= 6) {
            $job_details['employer']['name'] = $message[0];
            $job_details['employer']['address'] = $message[1];
            $job_details['employer']['city'] = $message[2];
            $job_details['position'] = $message[3];
            $job_details['subject'] = $message[4];
            $job_details['employer']['email'] = $message[5];

            $user_data = $this->bot_user->personal_data;
            $user_data = json_decode($user_data, true);

            $documents = $this->bot_user->documents;
            $documents = json_decode($documents, true);

            $mpdf = new Mpdf([
                'format' => 'Letter',
                'default_font_size' => 12,
                'default_font' => 'Arial',
                'margin_left' => 25,
                'margin_right' => 25,
                'margin_top' => 20,
                'margin_bottom' => 20
            ]);
            $current_date = date('d') . ' ' . $this->month(date('m')) . ' ' . date('Y');
            $job_application_letter = view('lamaran', compact('job_details', 'user_data', 'documents', 'current_date'))->render();
            
            $mpdf->WriteHTML($job_application_letter);
            $pdf_output = $mpdf->Output('job.pdf', 's');

            Storage::put('job.pdf', $pdf_output);

            $selected_cv = $this->bot_user->documents()->where('name', $this->bot_user->mem)->first();

            $merger = new Merger;
            $merger->addFile(Storage::path('job.pdf'));
            $merger->addFile(Storage::path($selected_cv->url));
            $merger->addFile(Storage::path($this->bot_user->documents_url));
            $createdPdf = $merger->merge();
            Storage::put('lamaran.pdf', $createdPdf);

            $ilovepdf = new Ilovepdf('project_public_81768907f7cd598fcc2881cd96bd78df_hUqNHd28fc701cb7e1e67b79bc26adf803723', 'secret_key_ad8ed41d37c48bd04d2f7b025bc0d1b2_9kuyl0ab659237b159812346de8ffeea80385');
            $task = $ilovepdf->newTask('compress');
            $file = $task->addFile(Storage::path('lamaran.pdf'));
            $task->execute();
            $task->download(Storage::path(''));

            Mail::to($job_details['employer']['email'])->send(new ApplyJob($job_details, $user_data, $current_date));

            $this->sendMessage('Berhasil dikirim!');

            $this->bot_user->save();
        }
        else {
            $this->sendMessage('Format tidak valid');
            return;
        }
    }

    private function month($month)
    {
        switch ($month) {
            case '01':
                return 'Januari';
                break;
            case '02':
                return 'Februari';
                break;
            case '03':
                return 'Maret';
                break;
            case '04':
                return 'April';
                break;
            case '05':
                return 'Mei';
                break;
            case '06':
                return 'Juni';
                break;
            case '07':
                return 'Juli';
                break;
            case '08':
                return 'Agustus';
                break;
            case '09':
                return 'September';
                break;
            case '10':
                return 'Oktober';
                break;
            case '11':
                return 'November';
                break;
            case '12':
                return 'Desember';
                break;
            default:
                return 'Januari';
                break;
        }
    }

}
