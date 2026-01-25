<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Mail\SupportTicketMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function submitTicket(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            // 1. Simpan ke Database
            $ticket = SupportTicket::create([
                'sender_name' => $request->name,
                'sender_email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
                'status' => 'Open',
            ]);

            // 2. Kirim notifikasi email ke tim dukungan
            // GANTI BARIS BERIKUT DENGAN ALAMAT EMAIL TUJUAN YANG BENAR
            $supportEmail = 'ganti_dengan_email_support_yang_benar@gmail.com'; // <--- PERBAIKI INI!

            Mail::to($supportEmail)->send(new SupportTicketMail([
                'sender_name' => $request->name,
                'sender_email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ]));

            return response()->json(['message' => 'Tiket dukungan berhasil dikirimkan!', 'ticket_id' => $ticket->id], 201);

        } catch (\Exception $e) {
            // Ini akan mencatat error seperti 'View not found' atau error SMTP
            Log::error("Error saat mengirim tiket dukungan: " . $e->getMessage());
            return response()->json(['message' => 'Gagal mengirim tiket. Error server: ' . $e->getMessage()], 500);
        }
    }
}
