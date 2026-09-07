<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Twig\Markup;

class PaginationService extends AbstractController
{
    private $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function selectPaginator(int $total, int $currentPage, int $perPage = 15): array
    {
        // Calcul du nombre total de pages
        $totalPages = max(1, ceil($total / $perPage));

        // Validation de la page courante
        $currentPage = max(1, min($currentPage, $totalPages));

        // Calcul des données de pagination
        $offset = ($currentPage - 1) * $perPage;

        return [
            'total_items' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'html' => new Markup($this->generateSimpleHtml($totalPages, $currentPage), 'UTF-8'),
        ];
    }

    private function generateSimpleHtml(int $totalPages, int $currentPage): string
    {
        // Options pour le select
        $options = '';
        for ($i = 1; $i <= $totalPages; $i++) {
            $selected = $i == $currentPage ? 'selected' : '';
            $options .= "<option value=\"$i\" $selected>Page $i</option>";
        }

        return <<<HTML
        <div class="d-flex align-items-center justify-content-center">
            <select
                data-js="_PaginationService={change:goTo}"
                class="pagination--select form-control form-control-sm" 
                _onchange="window.location.href = '?page=' + this.value"
                style="border-top-right-radius: 0; border-bottom-right-radius: 0; width: 70px; text-align: center"
                >
                $options
            </select>
            
            <input
                class="pagination--input form-control form-control-sm"
                type="number" 
                id="pageInput" 
                min="1" 
                max="$totalPages" 
                placeholder="Page"
                style="border-radius: 0; width: 70px; text-align: center"
                value="$currentPage"
            >
            <button
                data-js="_PaginationService={click:goTo}"
                class="pagination--btn btn btn-sm btn-p"
                style="border-top-left-radius: 0; border-bottom-left-radius: 0"
                >
                Go
            </button>
            <span class="pagination--text text-nowrap fs-8 ms-2">Page $currentPage / $totalPages</span>
        </div>
        <script>
            setTimeout(() => {
                __.dataJs({
                    _PaginationService: {
                        goTo: function(t) {
                            let page = t.val();
                            if( t.is('select') ) {
                                t.next('.pagination--input').val(page);
                            }
                            else if( t.is('button') ) {
                                page = t.prev('.pagination--input').val();
                            }
                            const url = new URL(window.location.href);
                            url.searchParams.set('page', page);
                            TurboNav.allow_ajax = true;
                            TurboNav.get_page(url.toString());
                        }
                    }
                });
            }, 300);
        </script>
        HTML;
    }
}
