<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class UserService extends AbstractController
{
    public $please;
    public $session;
    public $is;
    public $log;
    public $User;

    public function __construct(PleaseService $please, RequestStack $requestStack)
    {
        $this->please = $please;
        $this->session = $requestStack->getSession();
        $this->User = $this->please->serve('security')->getCurrentUser();
        $this->log = $please->serve('log');
    }

    public function login($params)
    {
        $params = array_merge([
            'ifNotXHR' => null,
            'onAlreadyLoggedIn' => null,
            'isValid' => null,
            'validator' => [],
            'finder' => function ($posted) {},
            'formView' => function ($userWasNotFound = null, $params) {}, // will be TRUE if user was not found
            'onSuccess' => function ($found, $params) {},
            'onError' => function ($message) {
                $redir = $this->please->serve('url')->getHome();
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'redirect' => $redir,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($redir);
            }
        ], $params);

        try {

            if ($this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('login', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            $this->is = 'Auth';

            //forcing
            $params['isGranted'] = [
                "byUserRoles" => ['__NONE__']
            ];

            if (isset($params['dd'])) { // <<- 31.10.23
                dd($params);
            }

            if (false === $this->please->serve('security')->__isGranted($params) || $this->User) {
                $this->log->writeDB('warning', 'login', [
                    'message' => 'utilisateur déjà connecté'
                ], true);
                if (is_callable($params['onAlreadyLoggedIn'])) {
                    return $params['onAlreadyLoggedIn']($this->User);
                } else {
                    return $this->please->serve('fallbacks_hook')->defaultFallback('onAlreadyLoggedIn');
                }
            }

            if ($this->please->getRequest()->isMethod('POST')) {
                return $this->please->serve('crud')->checkDataValidity($params, function ($params) {

                    if (is_null($params['isValid']) || !empty($params['validator'])) {

                        $all = $this->please->getRequest()->request->all();

                        $found = $params['finder']($all, collection('users'));

                        if (!$found) {
                            $this->log->write('warning', 'login', ['message' => 'tentative de connexion échouée']);
                            return $params['formView'](true, $params);
                        }
                        // lets check _stayConnected
                        if ($stayConnected = $this->please->getInput('_stayConnected')) {
                            $this->please->serve('crud')->basicUpdate([
                                'collection' => 'users',
                                'finder' => function () use ($found) {
                                    return $found;
                                },
                                'sanitizer' => function () use ($stayConnected) {
                                    return ['_stayConnected' => $stayConnected];
                                }
                            ]);
                        }
                        $this->please->serve('security')->getSession()->set('User', $found);
                        $this->log->writeDB('success', 'login', [
                            'document' => $found,
                            'message' => "<a href='#'><b>{$found['username']}</b><a/> connecté avec succès"
                        ], true);
                        $this->_hookOnSuccess('user', $found, [$found['id']], 'login', $all);
                        return $params['onSuccess']($found, $params);
                    } else {
                        $this->log->writeDB('warning', 'login', [
                            'message' => 'tentative de connexion échouée'
                        ], true);
                        return $params['formView'](true, $params);
                    }
                }, null, 'login');
            }
            $this->log->writeDB('success', 'login.show', [
                'message' => 'formulaire de connexion affiché avec succès'
            ], true);
            return $params['formView'](null, $params);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'login');
        }
    }

    public function loginInstant($params)
    {
        $params = array_merge([
            'ifNotXHR' => null,
            'user' => null,
            'onSuccess' => function ($user) {}
        ], $params);

        try {

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            $user = $params['user'];

            $this->please->serve('security')->getSession()->set('User', $user);

            $all = $this->please->getRequest()->request->all();
            $this->_hookOnSuccess('user', $user, [$user['id']], 'loginInstant', $all);
            $this->log->writeDB('success', 'loginInstant', [
                'document' => $user,
                'message' => 'utilisateur reconnecté de façon instantanée avec succès'
            ], true);
            return $params['onSuccess']($user);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'loginInstant');
        }
    }

    public function logout($params)
    {
        $params = array_merge([
            'ifNotXHR' => null,
            'beforeSend' => function () {},
            'onAlreadyLoggedOut' => function () {},
            'onSuccess' => function ($cachedUser) {},
            'onComplete' => function ($status) {},
        ], $params);

        try {

            if (is_string($params['ifNotXHR'])) {
                if (!$this->please->isXHR()) {
                    return $this->please->XHRReturn($params);
                }
            }

            $params['beforeSend']();

            if (!$this->User) {
                $status = ['isAlreadyLoggedOut' => true];
                $response = $params['onAlreadyLoggedOut']();
                $this->log->writeDB('warning', 'logout', [
                    'message' => 'utilisateur déjà déconnecté'
                ], true);
            } else {

                $cachedUser = $this->User;

                $this->please->serve('security')->getSession()->set('User', null);

                $all = $this->please->getRequest()->request->all();

                $status = ['isSuccess' => true];
                $response = $params['onSuccess']($cachedUser);
                $this->_hookOnSuccess('user', $cachedUser, [$cachedUser['id']], 'logout', $all);
                $this->log->writeDB('success', 'logout', [
                    'document' => $cachedUser,
                    'message' => 'utilisateur déconnecté avec succès'
                ], true);
            }

            $params['onComplete']($status);

            return $response;
        } catch (\Exception $e) {
            return $this->log->exception($e, 'logout');
        }
    }

    public function reLogin($params)
    {
        $params = array_merge([
            'user' => null,
            'onSuccess' => function ($cachedUser) {},
        ], $params);

        try {
            return $this->logout([
                'onSuccess' => function ($cachedUser) use ($params) {
                    return $this->loginInstant([
                        'user' => $params['user'] ?? $cachedUser,
                        'onSuccess' => function () use ($params, $cachedUser) {
                            $all = $this->please->getRequest()->request->all();
                            $this->_hookOnSuccess('user', $cachedUser, [$cachedUser['id']], 'reLogin', $all);
                            $this->log->writeDB('success', 'reLogin', [
                                'message' => 'utilisateur reconnecté avec succès'
                            ], true);
                            return $params['onSuccess']($cachedUser);
                        },
                    ]);
                }
            ]);
        } catch (\Exception $e) {
            return $this->log->exception($e, 'reLogin');
        }
    }

    public function forgot($params)
    {
        $params = array_merge([
            'isValid' => null,
            'validator' => [],
            //'onNotFound' => function ($emailNotFound = null) {}, // will be TRUE if user was not found
            'finder' => function ($posted, $collection) {},
            'onComplete' => function () {},
            //'onFound' => function ($user) {},
            'onError' => function ($message) {
                if ($this->please->isXHR()) {
                    return $this->please->serve('response')->jsonResponse([
                        'reload' => true,
                        'swal' => [
                            'type' => 'error',
                            'title' => $message
                        ]
                    ]);
                }
                return $this->redirect($this->please->getReferer());
            }
        ], $params);

        try {

            if ($this->please->serve('security')->csrfTokenIsInvalid()) {
                $this->log->csrf('forgot', $params);
                return $params['onError']("Non-valid CSRF token");
            }

            //forcing
            $params['isGranted'] = ["byUserRoles" => ['__NONE__']];

            if (false === $this->please->serve('security')->__isGranted($params)) {
                $this->log->unGranted('forgot', $params);
                return is_callable($otherwise = $this->_otherwise($params)) ? $otherwise() : $this->please->serve('fallbacks_hook')->defaultFallback('otherwise');
            }

            $params['_token'] = sha1(uniqid());

            if ($this->please->getRequest()->isMethod('POST')) {

                return $this->please->serve('crud')->checkDataValidity($params, function ($params) {

                    $all = $this->please->getRequest()->request->all();

                    if (is_null($params['isValid']) || !empty($params['validator'])) {

                        $collection = collection('users');

                        $user = $params['finder']($all, $collection);

                        $this->_hookOnSuccess('user', $user, [$user['id'] ?? null], 'login', $all);

                        return $params['onComplete']($all, $user);
                        /*if (!$user) {
                        return $params['onNotFound'](true);
                    }
                    return $params['onFound']($user);*/
                    }
                    return $params['onComplete']($all);
                }, null, 'forgot');
            }

            return $params['onComplete']();
        } catch (\Exception $e) {
            return $this->log->exception($e, 'forgot');
        }
    }

    // private function outGoingEmailConfirmation($params)
    // {

    //     $params = array_merge([
    //         'subject' => "Validation de compte",
    //         'subscriber' => null,
    //         'inComingEmailConfirmationRouteName' => 'inComingEmailConfirmation',
    //         'body' => function($mail, $subscriber, $link, $token, $mediaService, $urlService){},
    //         'onSuccess' => function($subscriber, $reSendLink, $validationLink){},
    //         'onError' => function($e){}
    //     ], $params);

    //     $params['isGranted'] = [
    //         "byUserRoles" => ['__NONE__']
    //     ];

    //     if(false === $this->please->serve('security')->__isGranted($params)){
    //         if(is_callable($otherwise = $this->_otherwise($params))){
    //             //devloper otherwise
    //             return $otherwise();
    //         }
    //         else {
    //             //default otherwise
    //             return $this->_defaultFallback('otherwise');
    //         }
    //     }

    //     return $this->basicEdit([
    //         'finder' => function () use ($params) {
    //             return $params['subscriber'];
    //         },
    //         'sanitizer' => function ($handled) {
    //             $validationToken = sha1(uniqid());
    //             $handled->setValidationToken($validationToken);
    //             return $handled;
    //         },
    //         'onSuccess' => function ($subscriber) use ($params) {

    //             $envS = $this->please->serve('env')->getAppEnv('env');

    //             $token = $subscriber->getValidationToken();
    //             $subscriberEmail = $subscriber->getEmail();
    //             $validationLink = trim($envS->getAppEnv('APP_ORIGIN'), '/') . $this->generateUrl($params['inComingEmailConfirmationRouteName'], ['token' => $token]);
    //             $reSendLink = $this->generateUrl($this->request->attributes->get('_route'), ['email' => $subscriberEmail]);


    //             $MAILER = explode('|', $envS->getAppEnv('MAILER'));
    //             $username = $MAILER[0];

    //             return $this->please->sendEmail([
    //                 'subject' => $params['subject'],
    //                 'from' => [$username => $envS->getAppEnv('APP_NAME')],
    //                 'to' => [$subscriberEmail => $this->getUserFullName($subscriber)],
    //                 'body' => function( $mail, $mediaService, $urlService ) use ($params, $subscriber, $validationLink, $token) {
    //                     return $params['body']( $mail, $subscriber, $validationLink, $mediaService, $urlService, $token );
    //                 },
    //                 'onSuccess' => function() use ($params, $subscriber, $reSendLink, $validationLink, $token) {
    //                     return $params['onSuccess']($subscriber, $reSendLink, $validationLink, $token);
    //                 },
    //                 'onError' => function($e){
    //                     return $params['onError']($e);
    //                 }
    //             ]);
    //         }
    //     ]);
    // }

    // private function outGoingEmailRetrieveAccount($params)
    // {
    //     //Required to create <input type="hidden" name="forgot_token" />
    //     //into your form
    //     $params = array_merge([
    //         'subject' => "Récupération de compte",
    //         'from' => ['me@domain.com'],
    //         'user' => null,
    //         'inComingEmailRetrieveAccountRouteName' => 'inComingEmailRetrieveAccount',
    //         'body' => function($mail, $user, $link, $mediaService, $urlService){},
    //         'onError' => function($params){},
    //         'onSuccess' => function($user, $reSendLink, $token){},
    //     ], $params);

    //     //forcing
    //     $params['isGranted'] = [
    //         "byUserRoles" => ['__NONE__']
    //     ];

    //     return $this->basicEdit([
    //         'finder' => function () use ($params) {
    //             return $params['user'];
    //         },
    //         'sanitizer' => function ($handled) {
    //             $forgot_token = sha1(uniqid());
    //             $handled->setForgotToken($forgot_token);
    //             return $handled;
    //         },
    //         'onSuccess' => function ($user) use ($params) {
    //             $token = $user->getForgotToken();
    //             $userEmail = $user->getEmail();
    //             $retrieveLink = trim($this->getBundleService('env')->getAppEnv('APP_ORIGIN'), '/') . $this->generateUrl($params['inComingEmailRetrieveAccountRouteName'], ['token' => $token]);
    //             $reSendLink = $this->generateUrl($this->request->attributes->get('_route'), ['id' => $user->getId()]);

    //             $envS = $this->getBundleService('env');
    //             $MAILER = explode('|', $envS->getAppEnv('MAILER'));
    //             $username = $MAILER[0];

    //             return $this->sendEmail([
    //                 'subject' => $params['subject'],
    //                 'from' => [$username => $envS->getAppEnv('APP_NAME')],
    //                 'to' => [$userEmail => $this->getUserFullName($user)],
    //                 'body' => function($mail, $mediaService, $urlService) use ($params, $user, $retrieveLink) {
    //                     return $params['body']( $mail, $user, $retrieveLink, $mediaService, $urlService );
    //                 },
    //                 'onSuccess' => function() use ($params, $user, $reSendLink, $token, $retrieveLink) {
    //                     return $params['onSuccess']($user, $reSendLink, $token, $retrieveLink);
    //                 },
    //                 'onError' => function($e){
    //                     return $params['onError']($e);
    //                 }
    //             ]);
    //         }
    //     ]);
    // }

    public function getUserFullName($user = null)
    {
        $u = $user ?? $this->User;
        $fullname = trim(attr($u, 'lastname') . ' ' . attr($u, 'firstname'));
        return empty($fullname) ? ($u['username'] ?? '--') : $fullname;
    }

    public function getUserProp($user, $prop, $criteria = [])
    {
        switch ($prop) {
            case 'prev':
                return collection('users')->findOneBy(array_merge([
                    'enabled' => 'on',
                    'approved' => 'on',
                    'created_at' => ['<', $user['created_at']]
                ], $criteria));

            case 'next':
                return collection('users')->findOneBy(array_merge([
                    'enabled' => 'on',
                    'approved' => 'on',
                    'created_at' => ['>', $user['created_at']]
                ], $criteria));

            default:
                return null;
        }
    }

    public function _setFlash($key, $duration = 2)
    {
        $this->please->serve('flash')->setFlash($key, attr($this->please->serve('security')->getCurrentUser(), 'id'), $duration);
    }

    private function _otherwise($params)
    {
        if (is_array($params['isGranted']) && isset($params['isGranted']['otherwise']) && is_callable($params['isGranted']['otherwise'])) {
            return $params['isGranted']['otherwise'];
        }
        return false;
    }

    private function _hookOnSuccess(string $coll_name, $document, array $doc_ids, string $action, array $posted = [])
    {
        if ((new Filesystem())->exists($this->please->serve('dir')->getProjectDir() . '/src/Hook/OnSuccessHook.php')) {
            if (method_exists(\App\Hook\OnSuccessHook::class, 'hookOnSuccess') && $this->please->prevContainer->has('on_success_hook')) {
                $r = $this->please->prevContainer->get('on_success_hook')->hookOnSuccess($coll_name, $document, $doc_ids, $action, $posted);
            }
        }
    }
}
