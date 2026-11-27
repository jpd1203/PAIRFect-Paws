<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\ResearchPlacement;
use App\Services\ResearchPrivateMedia;
use App\Support\ResearchMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ResearchMediaController extends Controller
{
    public function document(ResearchPlacement $placement, ResearchPrivateMedia $media)
    {
        abort_unless(in_array($placement->document_mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);
        abort_unless($placement->document_consent_at, 404);

        return $media->response('documents', $placement->user_id, $placement->document_path, $placement->document_mime_type, download: true);
    }

    public function documentOcr(ResearchPlacement $placement)
    {
        abort_unless(ResearchMode::ready() && config('research.retain_ocr_text') === true, 404);
        abort_unless($placement->document_path && $placement->document_consent_at && $placement->document_verification_status, 404);

        $retentionConsent = $placement->document_consent_version === ResearchMode::OCR_TEXT_CONSENT_VERSION;
        $text = $retentionConsent ? $placement->ocr_extracted_text : null;

        return response()->view('admin.application.research-document-ocr', compact('placement', 'retentionConsent', 'text'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function video(int $report, ResearchPrivateMedia $media)
    {
        abort_unless(ResearchMode::ready(), 404);
        $log = DB::table('research_checkins')->join('research_placements', 'research_placements.id', '=', 'research_checkins.placement_id')
            ->where('research_checkins.id', $report)->where('research_placements.status', ApplicationStatus::Approved->value)
            ->whereNotNull('research_placements.received_at')->first(['placement_id', 'video_path', 'video_mime_type']);
        abort_unless($log && in_array($log->video_mime_type, ['video/webm', 'video/mp4'], true), 404);

        return $media->response('checkins', $log->placement_id, $log->video_path, $log->video_mime_type);
    }

    public function ownedReceipt(Request $request, ResearchPlacement $placement, ResearchPrivateMedia $media)
    {
        abort_unless((int) $placement->user_id === (int) $request->user()->id, 404);

        return $this->receipt($placement, $media);
    }

    public function receipt(ResearchPlacement $placement, ResearchPrivateMedia $media)
    {
        $receipt = $placement->handover_receipt ?? [];
        abort_unless($placement->status === ApplicationStatus::Approved && $placement->received_at && ($receipt['outcome'] ?? null) === 'received', 404);
        abort_unless(in_array($receipt['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $media->response('receipts', $placement->id, $receipt['path'] ?? null, $receipt['mime']);
    }
}
