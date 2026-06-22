<?php

namespace App\Http\Controllers;

use App\Repositories\OrderRepository;
use App\Services\PdfTicketService;
use Illuminate\Http\Request;

class TicketPdfController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected PdfTicketService $pdfTicketService,
    ) {
    }

    public function orderPdf(Request $request, int $orderId)
    {
        $order = $this->orderRepository->findById($orderId);

        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $html = $this->pdfTicketService->renderOrderTicketsHtml($orderId);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="pedido-'.$orderId.'-ingressos.html"',
        ]);
    }
}
