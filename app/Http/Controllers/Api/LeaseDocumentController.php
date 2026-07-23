<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLeaseDocumentRequest;
use App\Http\Resources\LeaseDocumentResource;
use App\Models\Lease;
use App\Models\LeaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaseDocumentController extends Controller
{
    public function index(Lease $lease): AnonymousResourceCollection
    {
        $documents = $lease->documents()
            ->with('uploader')
            ->latest()
            ->get();

        return LeaseDocumentResource::collection($documents);
    }

    public function store(StoreLeaseDocumentRequest $request, Lease $lease): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store("leases/{$lease->id}", 'local');

        $document = $lease->documents()->create([
            'uploaded_by' => $request->user()->id,
            'type' => $request->validated('type') ?? 'other',
            'title' => $request->validated('title'),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        $document->load('uploader');

        return (new LeaseDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Lease $lease, LeaseDocument $document): StreamedResponse|JsonResponse
    {
        if ($document->lease_id !== $lease->id) {
            return response()->json(['message' => 'Belge bu sözleşmeye ait değil.'], 404);
        }

        if (! Storage::disk('local')->exists($document->path)) {
            return response()->json(['message' => 'Dosya bulunamadı.'], 404);
        }

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name
        );
    }

    public function destroy(Lease $lease, LeaseDocument $document): JsonResponse
    {
        if ($document->lease_id !== $lease->id) {
            return response()->json(['message' => 'Belge bu sözleşmeye ait değil.'], 404);
        }

        if (Storage::disk('local')->exists($document->path)) {
            Storage::disk('local')->delete($document->path);
        }

        $document->delete();

        return response()->json(['message' => 'Belge silindi.']);
    }
}
