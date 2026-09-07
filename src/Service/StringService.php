<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DOMXPath;
use DOMDocument;
use Twig\Markup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\__Html2TextService;

class StringService extends AbstractController
{
    protected $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function getSlug(string $string, $replacement = '-', $lowercase = true): string
    {
        // Tout en une seule opération de nettoyage
        $string = preg_replace([
            '#^([a-z]+://)?([a-z0-9.-]+\.)*[a-z0-9.-]+/#', // Domaines
            '/[?=&+]/', // Caractères spéciaux d'URL
            '~[^\pL\d]+~u', // Caractères non alphanumériques
        ], $replacement, $this->getAccentsLess($string));

        // Translittération
        $string = iconv('utf-8', 'us-ascii//TRANSLIT', $string);

        // Nettoyage final
        $escapedReplacement = preg_quote($replacement, '~');
        $string = preg_replace('~[^\w' . $escapedReplacement . ']~', '', $string);
        $string = trim($string, $replacement);
        $string = preg_replace('~' . $escapedReplacement . '+~', $replacement, $string);

        if ($lowercase) {
            $string = strtolower($string);
        }

        return $string ?: 'n-a';
    }

    public function getTag(string $string): string
    {
        return trim(preg_replace('/\s+/', ', ', str_ireplace(',', '', $string)), ', ');
    }

    public function ellipsisText(string $string, int $max = 100, string $append = '…'): string
    {
        return strlen($string) > $max ? substr($string, 0, $max) . $append : $string;
    }

    public function getAccentsLess(string $string, $charset = 'utf-8'): string
    {
        // D'ABORD remplacer les caractères spéciaux d'URL par des espaces temporaires
        $string = str_replace(['?', '=', '&'], ' ', $string);

        $string = htmlentities($string, ENT_NOQUOTES, $charset);
        $string = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $string);
        $string = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $string); // pour les ligatures e.g. '&oelig;'
        $string = preg_replace('#&[^;]+;#', '', $string); // supprime les autres caractères
        $string = trim($string); // supprime les autres caractères

        // Remplacer les espaces temporaires par le séparateur final (sera fait dans getSlug)
        return $string;
    }

    public function getArrayToString(array $array, $separateur = ' '): string
    {
        $string = '';
        foreach ($array as $valeur) {
            if (is_string($valeur)) {
                $string .= $valeur . $separateur;
            }
        }
        return trim($string);
    }

    public function getTrimAll(string $str, $what = null, $with = ' '): string
    {
        if ($what === null) {
            //  Character      Decimal      Use
            //  "\0"            0           Null Character
            //  "\t"            9           Tab
            //  "\n"           10           New line
            //  "\x0B"         11           Vertical Tab
            //  "\r"           13           New Line in Mac
            //  " "            32           Space
            $what = "\\x00-\\x20"; //all white-spaces and control chars
        }
        return trim(preg_replace("/[" . $what . "]+/", $with, $str), $what);
    }

    public function getQuerySearchQuery($params)
    {
        $params = array_merge([
            'keyword' => '',
            'columnsToSearchIn' => [],
            'operator' => 'LIKE',
            'condition' => 'AND',
            'handleSql' => null,
            'forEachKeywords' => function ($col, $keyWord) {
                return [
                    $col => "%$keyWord%"
                ];
            },
        ], $params);

        $sql = '';
        $parameters = [];
        $keywords = explode(' ', $params['keyword']);
        $handleSql = is_callable($params['handleSql']);
        for ($i = 0; $i < sizeof($keywords); $i++) {
            //$kw = htmlentities(trim($keywords[$i]));
            $keyWord = trim($keywords[$i]);
            if (strlen($keyWord) > 0) {
                if (!$handleSql) {
                    $sql .= ' (';
                }
                $j = 0;
                foreach ($params['columnsToSearchIn'] as $column) {

                    // lets make request on info with LIKE possible
                    // if( strpos($column, '[') !== false ){

                    //     $flag = ''
                    //     foreach($flags as $flag){
                    //         if( strpos($column, '|'.$flag) !== false ){
                    //             $flag = $flag;
                    //             $column = trim($column, '|'.$flag);
                    //         }
                    //     }
                    //     $column = trim($column, ']');
                    //     $colmunXpld = explode('[', $column);
                    //     if( sizeof($colmunXpld) == 2 ){

                    //     }
                    //     dd($colmunXpld, $flag);
                    // }
                    // else {

                    // }

                    $col = (str_ireplace('.', '_', $column)) . '_' . $i;

                    if ($handleSql) {
                        $res = (object) $params['handleSql'](
                            (object) [
                                'column' => $column,
                                'col' => $col . '_' . rand(0, 999999),
                                'keyword' => $keyWord,
                                'params' => $params
                            ]
                        );
                        $sql .= $res->sql;
                        $parameters = array_merge($parameters, $res->parameter);
                    } else {
                        $parameters = array_merge($parameters, $params['forEachKeywords']($col, $keyWord));
                        $sql .= $column . ' ' . $params['operator'] . ' :' . $col;
                        if ($j < sizeof($params['columnsToSearchIn']) - 1) {
                            $sql .= ' OR ';
                        }
                    }

                    $j++;
                }
                if (!$handleSql) {
                    $sql .= ') ';
                }
                if ($i < sizeof($keywords) - 1) {
                    $sql .= $params['condition'];
                }
            }
        };

        if (!$handleSql) {
            $sql = trim($sql, 'AND ');
        }
        return (object) [
            'sql' => $sql,
            'parameters' => $parameters,
        ];
    }

    public function getBootstrapAlert($message, $label = 'Error', $class = 'danger')
    {
        return "<p class='alert alert-$class'><b>$label:</b> $message.</p>";
    }

    public function getVisitorsCount()
    {
        $counterFile = $this->please->getBundleService('view')->getViewsDir("storage/counter.txt");
        if (!file_exists($counterFile)) {
            file_put_contents($counterFile, '');
        }
        if (!isset($_SESSION['counter'])) { // It's the first visit in this session
            $handle = fopen($counterFile, "r");
            if (!$handle) {
                return 0;
            } else {
                $counter = (int) fread($handle, 20);
                fclose($handle);
                $counter++;
                $handle = fopen($counterFile, "w");
                fwrite($handle, $counter);
                fclose($handle);
                $_SESSION['counter'] = $counter;
            }
        } else { // It's not the first time, do not update the counter but show the total hits stored in session
            $counter = $_SESSION['counter'];
            return $counter;
        }
    }

    public function sanitizeOutput($buffer)
    {

        $search = array(
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            //'/<!--(.|\s)*?->/' // Remove HTML comments
        );

        $replace = array(
            '>',
            '<',
            '\\1',
            //''
        );

        $buffer = preg_replace($search, $replace, $buffer);

        return $buffer;
    }

    public function encrypt($message, $key)
    {
        return urlencode(openssl_encrypt($message, "AES-128-ECB", $key));
    }

    public function decrypt($encrypted, $key)
    {
        return openssl_decrypt(urldecode($encrypted), "AES-128-ECB", $key);
    }

    /**
     * @param $n
     * @return string
     * Use to convert large positive numbers in to short form like 1K+, 100K+, 199K+, 1M+, 10M+, 1B+ etc
     */
    public function fileWeightFormatShort($n)
    {
        if ($n >= 0 && $n < 1000) {
            // 1 - 999
            $n_format = floor($n);
            $suffix = '';
        } else if ($n >= 1000 && $n < 1000000) {
            // 1k-999k
            $n_format = $n / 1000;
            $suffix = 'Ko';
        } else if ($n >= 1000000 && $n < 1000000000) {
            // 1m-999m
            $n_format = $n / 1000000;
            $suffix = 'Mo';
        } else if ($n >= 1000000000 && $n < 1000000000000) {
            // 1b-999b
            $n_format = $n / 1000000000;
            $suffix = 'Bo';
        } else if ($n >= 1000000000000) {
            // 1t+
            $n_format = $n / 1000000000000;
            $suffix = 'To';
        }

        $n_format = number_format((float) $n_format, 2, '.', '');
        return !empty($n_format . $suffix) ? $n_format . $suffix : 0;
    }

    public function priceFormat($number, int $decimals = 0, string $dec_point = ',', string $thousands_sep = ' '): string
    {
        $number = preg_replace('/\s+/', '', $number);
        //$number = str_replace('.', '', $number);
        return number_format($number, $decimals, $dec_point, $thousands_sep);
    }

    public function toValidCurrency($chaine)
    {
        // Supprime espaces, apostrophes, points comme séparateurs
        $nettoye = preg_replace('/[\s\'.]/', '', $chaine);

        // Remplace virgule décimale par point
        $nettoye = str_replace(',', '.', $nettoye);

        // Vérifie si c'est un nombre valide
        if (is_numeric($nettoye)) {
            return floatval($nettoye);
        }

        return 0; // ou throw new Exception("Format invalide")
    }

    public function countViews($number = null, $viewsText = ' vues', $noViewsText = 'Aucune vue')
    {
        if ($number == 0) {
            $views = $noViewsText;
            $viewsText = '';
        } elseif ($number == 1) {
            $views = $number;
            $viewsText = str_replace('s', '', $viewsText);
        } elseif ($number <= 999) {
            $views = $number;
        } elseif ($number < 1000000) {
            $views = round($number / 1000, 2) . ' k';
        } elseif ($number < 1000000000) {
            $views = round($number / 1000000, 2) . ' M';
        } elseif ($number >= 1000000000) {
            $views = round($number / 1000000000, 2) . ' B';
        }

        return $views . '' . $viewsText;
    }

    public function uId($length = 8, $prefix = ''): string
    {
        return $prefix . '' . substr(sha1(uniqid()), 0, $length);
    }

    public function uId8(): string
    {
        return substr(crc32(uniqid()), 0, 8);
    }

    public function facebookPage($pagename): string
    {
        return '<iframe 
            src="https://www.facebook.com/plugins/page.php?href=' . $pagename . '&width=600&&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=false"
            style="border:none;overflow:hidden" 
            scrolling="no" 
            frameborder="0" 
            allowfullscreen="true" 
            allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">
        </iframe>';
        return '<iframe name="fd12c61b2fc764" data-testid="fb:page Facebook Social Plugin" title="fb:page Facebook Social Plugin" frameborder="0" allowtransparency="true" allowfullscreen="true" scrolling="no" allow="encrypted-media" src="https://www.facebook.com/v17.0/plugins/page.php?adapt_container_width=true&amp;app_id=&amp;channel=https%3A%2F%2Fstaticxx.facebook.com%2Fx%2Fconnect%2Fxd_arbiter%2F%3Fversion%3D46%23cb%3Df3e26556acaa8d%26domain%3Dlocalhost%26is_canvas%3Dfalse%26origin%3Dhttp%253A%252F%252Flocalhost%252Ff12909d38934b18%26relation%3Dparent.parent&amp;container_width=281&amp;hide_cover=false&amp;href=https%3A%2F%2Fwww.facebook.com%2F' . $pagename . '&amp;locale=en_US&amp;sdk=joey&amp;show_facepile=false&amp;show_posts=false&amp;small_header=false&amp;width=600"></iframe>';
    }

    public function renderMetas($keys = []): string
    {
        $post = $this->please->getGlobal('post');
        $post_id = $post['id'] ?? null;
        $url_serv = $this->please->serve('url');

        if ($post_id) {
            $category = attr($post, 'category');
            $metas = attr(collection($coll_name)->findOneBy(['type' => 'meta']), 'metas', []);
            $curr_route = $this->please->getRequest()->get('_route');
            $render = '';
            foreach ($metas as $k => $meta) {
                if (in_array($k, $keys)) {
                    if ($meta_route = ($meta['route'] ?? null) && $content = ($meta['content'] ?? null)) {
                        $title = $post['title'];
                        $content = str_ireplace('__id__', $post_id, $content);
                        $content = str_ireplace('__name__', $title, $content);
                        $content = str_ireplace('__title__', $title, $content);
                        $content = str_ireplace('__description__', trim(strip_tags($post['description'])), $content);
                        $content = str_ireplace('__url__', $url_serv->getHref($post), $content);
                        $content = str_ireplace('__image__', $post['image'], $content);
                        $content = str_ireplace('__brand__', attr($post, 'brand.title', 'Aucune'), $content);
                        $content = str_ireplace('__price__', attr($post, 'min_max.price_min'), $content);
                        $content = str_ireplace('__category_id__', attr($category, 'id'), $content);
                        $render .= $content;
                    }
                }
            }
        }
        return $render ?? '';
    }

    public function disqusThread($page_url = null, $page_identifier = null)
    {
        $disqusKey = $this->please->serve('env')->getAppEnv('DISQUS_THREAD_KEY');
        $page_url = $page_url ?? $this->please->serve('url')->getCurrentUrlQueryLess();
        $page_identifier = $page_identifier ?? attr($this->please->getGlobal('post'), 'id');

        if ($disqusKey && $page_url && $page_identifier) {
            return '<script>var disqus_config=function(){this.page.url="' . $page_url . '",this.page.identifier="' . $page_identifier . '"};!function(){var e=document,t=e.createElement("script");t.src="https://' . $disqusKey . '.disqus.com/embed.js",t.setAttribute("data-timestamp",+new Date),(e.head||e.body).appendChild(t)}();</script><div id="disqus_thread"></div>';
        }
        return null;
    }

    public function i($code, $classNames = '')
    {
        return '<span class="iconify ' . $classNames . '" data-icon="' . $code . '" data-inline="false"></span>';
    }

    public function randomColor($colors = ['#2c3e50', '#0BC1E4', '#B24332', '#C55C23'])
    {
        $randomColor = $colors[array_rand($colors)];
        return $randomColor;
    }

    public function extractNode($dom, $selector = 'body')
    {
        if ($selector === 'body') {
            preg_match('/<body[^>]*>(.*?)<\/body>/s', $dom, $matches);
            return $matches[0] ?? null;
        }

        if (str_starts_with($selector, '.')) {
            $class = substr($selector, 1);
            // Regex pour trouver la balise avec la classe et capturer tout son contenu
            preg_match('/<([a-z]+)[^>]*class="[^"]*' . preg_quote($class, '/') . '[^"]*"[^>]*>(.*)<\/\1>/is', $dom, $matches);
            return $matches[0] ?? null;
        }

        if (str_starts_with($selector, '#')) {
            $id = substr($selector, 1);
            preg_match('/<([a-z]+)[^>]*id="' . preg_quote($id, '/') . '"[^>]*>(.*)<\/\1>/is', $dom, $matches);
            return $matches[0] ?? null;
        }

        preg_match('/<' . preg_quote($selector, '/') . '[^>]*>(.*)<\/' . preg_quote($selector, '/') . '>/is', $dom, $matches);
        return $matches[0] ?? null;
    }

    public function camelToSnake($input)
    {
        // Gérer les acronymes (ex: BaseAssets -> base_assets)
        $pattern = '/([A-Z][a-z]+)/';
        $snake = preg_replace($pattern, '_$1', $input);
        $snake = strtolower($snake);
        $snake = ltrim($snake, '_');

        return $snake;
    }

    public function pascalCase($input)
    {
        $input = str_replace(['-', '_'], ' ', $input);
        $input = ucwords($input);
        return str_replace(' ', '', $input);
    }

    public function displayPrice(array $amounts, array $keys = ['prix_regulier', 'prix_de_vente'], string $currency = 'FCFA')
    {
        $regular = $amounts[$keys[0]] ?? 0;
        $sale = $amounts[$keys[1]] ?? 0;

        if ($sale > 0 && $sale < $regular) {
            $discount = round(($regular - $sale) / $regular * 100);
            return sprintf(
                '<span class="variation_option__price_regular">%s ' . $currency . '</span><span class="variation_option__price_sale">%s ' . $currency . '</span><span class="variation_option__badge bg-danger">-%s%%</span>',
                number_format($regular, 0, ',', ' '),
                number_format($sale, 0, ',', ' '),
                $discount
            );
        }

        $output = sprintf(
            '<span class="variation_option__price">%s ' . $currency . '</span>',
            number_format($regular, 0, ',', ' ')
        );

        return new Markup($output, 'UTF-8');
    }
}
