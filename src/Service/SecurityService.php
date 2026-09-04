<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class SecurityService extends AbstractController
{
    public $please;
    public $session;

    public function __construct(PleaseService $please, RequestStack $requestStack)
    {
        $this->please = $please;
        $this->session = $requestStack->getSession();
        $GLOBALS['MoSQLConfig'] = $this->please->setMoSQLConfig();
        require_once dirname(__FILE__) . '/../Helpers/Quant.php';
    }

    public function getSession()
    {
        return $this->session;
    }

    public function getCurrentUser()
    {
        return $this->getFreshUser();
    }

    public function isConnected()
    {
        return $this->getUserId() !== null;
    }

    public function getUserId()
    {
        return attr($this->getFreshUser(), 'id');
    }

    public function __isGranted($params)
    {
        $byUserId = false;
        $byUserRoles = false;
        $byTruthiness  = false;

        if (isset($params['isGranted']) && is_callable($params['isGranted'])) {
            $params['isGranted'] = $params['isGranted']();
        }

        if (is_array($params['isGranted']) && isset($params['isGranted']['byUserId'])) {
            $byUserIdIsRequired = true;
            $byUserId = $this->_isGrantedById($params['isGranted']['byUserId']);
        }
        if (is_array($params['isGranted']) && isset($params['isGranted']['byUserRoles']) && is_array($params['isGranted']['byUserRoles'])) {
            $byUserRolesIsRequired = true;
            $byUserRoles = $this->_isGrantedByRoles($params['isGranted']['byUserRoles']);
        }
        if (is_array($params['isGranted']) && isset($params['isGranted']['byTruthiness'])) {
            $byTruthinessIsRequired = true;
            $byTruthiness_param = $params['isGranted']['byTruthiness'];
            $byTruthiness = (is_callable($byTruthiness_param) ? $byTruthiness_param() : $byTruthiness_param) === true;
        }

        if (
            // no restriction
            (is_null($params['isGranted']) || empty($params['isGranted']))
            ||
            // restriction by id
            (isset($byUserIdIsRequired) && $byUserId)
            ||
            // restriction by role
            (isset($byUserRolesIsRequired) && $byUserRoles)
            ||
            // restriction by true/false
            (isset($byTruthinessIsRequired) && $byTruthiness)
        ) {
            return true;
        }

        return false;
    }

    public function userCan(string $role = 'admin', $strictRole = true, $user = null)
    {
        // if( !in_array($role, ['admin', 'edit', 'editor', 'moderate', 'moderator']) ){
        //     return false;
        // }
        $user = $this->getFreshUser($user ?? $this->please->getCurrentUser());

        if ($user) {

            $userRoles = attr($user, 'roles', []);

            if (is_array($userRoles)) {
                if ($strictRole) {
                    if (in_array($role, $userRoles)) {
                        return true;
                    }
                    return false;
                }
                if (
                    in_array($role, $userRoles)
                    ||
                    (in_array($role, ['edit', 'editor']) && in_array('editor', $userRoles))
                    ||
                    (in_array($role, ['moderate', 'moderator']) && in_array('moderator', $userRoles))
                    ||
                    (in_array($role, ['admin', 'administrator']) && in_array('administrator', $userRoles))
                    ||
                    (in_array('admin', $userRoles))
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function userIs(string $role = 'admin', $strictRole = true, $user = null)
    {
        return $this->userCan($role, $strictRole, $user);
    }

    public function getCsrfToken()
    {
        $csrfTokens = $this->session->get('csrfTokens');
        if (!$csrfTokens || ($csrfTokens && count($csrfTokens) > 9)) {
            // lets delete token if 9 were already used
            $csrfTokens = [];
        }
        $token = bin2hex(random_bytes(32));
        $csrfTokens[$token] = true;
        $this->session->set('csrfTokens', $csrfTokens);
        return $token;
    }

    public function csrfTokenIsInvalid($GET = false)
    {
        // Si ce n'est pas une méthode à vérifier, le token est considéré comme valide
        if (
            !$this->please->getRequest()->isMethod('POST')
            &&
            !($GET === true && $this->please->getRequest()->isMethod('GET'))
        ) {
            return false; // PAS non valide = valide
        }

        $csrfTokens = $this->session->get('csrfTokens', []);
        $sentToken = $_POST['_token'] ?? $_GET['_token'] ?? null;

        // Token manquant = non valide
        if (!$sentToken) {
            return true;
        }

        // Retourne true si NON valide
        return !in_array($sentToken, $csrfTokens);
    }

    public function csrfTokenIsValid($GET = false)
    {
        return !$this->csrfTokenIsInvalid($GET);
    }

    public function userCanHandleAcf($acf = [], $user = null)
    {
        if ($user = $this->getFreshUser($user)) {
            $userAcfsIds = attr($user, 'acfToHandle', []);
            if (is_string($acf)) {
                $acf = collection('acf')->findOneBy(['name' => $acf]);
            }
            foreach ($userAcfsIds as $acfId) {
                if ($acfId == attr($acf, 'id')) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getFreshUser($user = null)
    {
        $user = $user ?? $this->session->get('User');
        $userId = attr($user, 'id', -1);
        $user = collection('users')->find($userId);
        return empty($user) ? null : $user;
    }

    public function logOutUser()
    {
        $this->please->serve('security')->getSession()->set('User', null);
    }

    public function userIsAuthenticated()
    {
        return $this->getCurrentUser() !== null;
    }


    private function _isGrantedByRoles($byUserRoles)
    {
        $ROLES = [];
        foreach ($byUserRoles as $role) {
            if ($role === '__ANY__') {
                return $this->getCurrentUser() !== null;
            } else if ($role === '__NONE__' || $role === '__ANON__' || $role === '__ANONYMOUS__') {
                return $this->getCurrentUser() === null;
            } else {
                //$ROLES[] = 'ROLE_' . trim(strtoupper($role));
                //$ROLES[] = trim(strtoupper($role));
                $ROLES[] = trim($role);
            }
        }
        return $this->_loopOverRoles($ROLES);
    }

    private function _isGrantedById($byUserId)
    {
        dd($byUserId);
        $userId = attr($this->getCurrentUser(), 'id');
        if ((is_int($byUserId) || is_string($byUserId)) && (int) $userId === (int) $byUserId && $byUserId !== 0) {
            return true;
        }
        return false;
    }

    private function _loopOverRoles(array $roles)
    {
        $userRoles = attr($this->getCurrentUser(), 'roles', []);
        if ($userRoles && is_array($userRoles)) {
            foreach ($userRoles as $userRole) {
                if (in_array($userRole, $roles)) {
                    return true;
                }
            }
        }
        return false;

        /*$userRoles = json_decode(attr($this->getCurrentUser(), '_roles', null));
        if($userRoles){
            foreach($userRoles as $userRole){
                if( in_array(strtoupper($userRole), $roles) ){
                    return true;
                }
            }
        }
        return false;*/
    }
}
