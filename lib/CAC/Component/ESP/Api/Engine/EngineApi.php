<?php

namespace CAC\Component\ESP\Api\Engine;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * E-Ngine API Client
 *
 * Api Client to connect to the E-Ngine ESP webservice.
 *
 * @author Crazy Awesome Company <info@crazyawesomecompany.com>
 */
class EngineApi implements LoggerAwareInterface
{
    /**
     * Api connection
     *
     * @var \SoapClient
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Api configuration
     *
     * @var array
     */
    private $config;

    public function __construct(array $config)
    {
        $this->config = array_replace_recursive(
            array(
                "wsdl" => null,
                "secure" => false,
                "domain" => "",
                "path" => "/soap/server.live.php",
                "customer" => "",
                "user" => "",
                "password" => "",
                "trace" => false,
                "mailinglist" => null,
            ),
            $config
        );
    }

    /**
     * Create a new E-Ngine Mailing from content
     *
     * @param string $htmlContent
     * @param string $textContent
     * @param string $subject
     * @param string $fromName
     * @param string $fromEmail
     * @param string $replyTo
     *
     * @return integer
     *
     * @throws EngineApiException
     */
    public function createMailingFromContent($htmlContent, $textContent, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
    {
        if (null === $replyTo) {
            $replyTo = $fromEmail;
        }

        if (null === $title) {
            $title = $subject;
        }

        $mailingId = $this->performRequest(
            'Mailing_createFromContent',
            utf8_encode($htmlContent),
            utf8_encode($textContent),
            utf8_encode($title),
            utf8_encode($subject),
            $fromName,
            $fromEmail,
            $replyTo
        );

        if (!is_numeric($mailingId)) {
            $e = new EngineApiException(sprintf('Could not create mailing from content. Engine Result: [%s]', $mailingId));
            $e->setEngineCode($mailingId);

            throw $e;
        }

        return $mailingId;
    }

    /**
     * Create a new mailing based on an E-Ngine Template
     *
     * @param integer $templateId
     * @param string  $subject
     * @param string  $fromName
     * @param string  $fromEmail
     * @param string  $replyTo
     * @param string  $title
     *
     * @return integer
     *
     * @throws EngineApiException
     */
    public function createMailingFromTemplate($templateId, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
    {
        return $this->createMailingFromTemplateWithReplacements($templateId, [], $subject, $fromName, $fromEmail, $replyTo = null, $title = null);
    }

    /**
     * Create a new mailing based on an E-Ngine Template
     *
     * @param integer $templateId
     * @param array   $replacements
     * @param string  $subject
     * @param string  $fromName
     * @param string  $fromEmail
     * @param string  $replyTo
     * @param string  $title
     *
     * @return integer
     *
     * @throws EngineApiException
     */
    public function createMailingFromTemplateWithReplacements($templateId, $replacements, $subject, $fromName, $fromEmail, $replyTo = null, $title = null)
    {
        if (null === $replyTo) {
            $replyTo = $fromEmail;
        }

        if (null === $title) {
            $title = $subject;
        }
        foreach ($replacements as $key => $val) {
            $replacements[$key] = utf8_encode($val);
        }

        $mailingId = $this->performRequest(
            'Mailing_createFromTemplateWithReplacements',
            $templateId,
            $replacements,
            utf8_encode($title),
            utf8_encode($subject),
            $fromName,
            $fromEmail,
            $replyTo
        );

        if (!is_numeric($mailingId)) {
            $e = new EngineApiException(sprintf('Could not create mailing from template. Engine Result: [%s]', $mailingId));
            $e->setEngineCode($mailingId);

            throw $e;
        }

        return $mailingId;
    }

    public function sendMailing($mailingId, array $users, $date = null, $mailinglistId = null)
    {
        if (null === $date) {
            $date = date("Y-m-d H:i:s");
        } elseif ($date instanceof \DateTime) {
            $date = $date->format("Y-m-d H:i:s");
        }

        if (null === $mailinglistId) {
            $mailinglistId = $this->config['mailinglist'];
        }

        // Check if users are set
        if (empty($users)) {
            throw new EngineApiException("No users to send mailing");
        }

        $result = $this->performRequest(
            'Subscriber_sendMailingToSubscribers',
            intval($mailingId),
            $date,
            $users,
            intval($mailinglistId)
        );

        if (!is_numeric($result)) {
            $e = new EngineApiException(sprintf('Could not send mailing [%d]. Engine Result: [%s]', $mailingId, $result));
            $e->setEngineCode($result);

            throw $e;
        }

        return $result;
    }

    public function sendMailingWithAttachment($mailingId, array $user, $date = null, $mailinglistId = null, $attachments = array())
    {
        if (null === $date) {
            $date = date("Y-m-d H:i:s");
        } elseif ($date instanceof \DateTime) {
            $date = $date->format("Y-m-d H:i:s");
        }

        if (null === $mailinglistId) {
            $mailinglistId = $this->config['mailinglist'];
        }

        // Check if user is set
        if (empty($user)) {
            throw new EngineApiException("No user to send mailing");
        }

        $result = $this->performRequest(
            'Subscriber_sendMailingToSubscriberWithAttachment',
            intval($mailingId),
            $date,
            $user,
            $attachments,
            intval($mailinglistId)
        );

        if (true !== $result) {
            $e = new EngineApiException(sprintf('Could not send mailing [%d]. Engine Result: [%s]', $mailingId, $result));
            $e->setEngineCode($result);

            throw $e;
        }

        return $result;
    }

    /**
     * Select a Mailinglist
     *
     * @param integer $mailinglistId
     *
     * @return string
     */
    public function selectMailinglist($mailinglistId)
    {
        return $this->performRequest('Mailinglist_select', intval($mailinglistId));
    }

    /**
     * Subscribe a User to a Mailinglist
     *
     * @param array   $user          The user data
     * @param integer $mailinglistId The mailinglist id to subscribe the user
     * @param bool    $confirmed     Is the user already confirmed
     *
     * @return string
     *
     * @throws EngineApiException
     */
    public function subscribeUser(array $user, $mailinglistId, $confirmed = false)
    {
        $result = $this->performRequest('Subscriber_set', $user, !$confirmed, $mailinglistId);

        if (!in_array($result, array('OK_UPDATED', 'OK_CONFIRM', 'OK_BEDANKT'))) {
            $e = new EngineApiException(sprintf('User not subscribed to mailinglist. Engine Result: [%s]', $result));
            $e->setEngineCode($result);

            throw $e;
        }

        return $result;
    }

    /**
     * Unsubscribe a User from a Mailinglist
     *
     * @param string  $email         The emailaddress to unsubscribe
     * @param integer $mailinglistId The mailinglist id to unsubscribe the user from
     * @param bool    $confirmed     Is the unsubscription already confirmed
     *
     * @return string
     *
     * @throws EngineApiException
     */
    public function unsubscribeUser($email, $mailinglistId, $confirmed = false)
    {
        $result = $this->performRequest('Subscriber_unsubscribe', $email, !$confirmed, $mailinglistId);

        if (!in_array($result, array('OK', 'OK_CONFIRM'))) {
            $e = new EngineApiException(sprintf('User not unsubscribed from mailinglist. Engine Result: [%s]', $result));
            $e->setEngineCode($result);

            throw $e;
        }

        return $result;
    }

    /**
     * Get all mailinglists of the account
     *
     * @return array
     */
    public function getMailinglists()
    {
        $result = $this->performRequest('Mailinglist_all');

        return $result;
    }

    /**
     * Get all unsubscriptions from a mailingslist of a specific time period
     *
     * @param integer   $mailinglistId
     * @param \DateTime $from
     * @param \DateTime $till
     *
     * @return array
     */
    public function getMailinglistUnsubscriptions($mailinglistId, \DateTime $from, \DateTime $till = null)
    {
        if (null === $till) {
            // till now if no till is given
            $till = new \DateTime();
        }

        $result = $this->performRequest(
            'Mailinglist_getUnsubscriptions',
            $from->format('Y-m-d H:i:s'),
            $till->format('Y-m-d H:i:s'),
            null,
            array('self', 'admin', 'hard', 'soft', 'spam', 'zombie'),
            $mailinglistId
        );

        return $result;
    }

    /**
     * Get Mailinglist Subscriber information
     *
     * @param integer $mailinglistId
     * @param string  $email
     * @param array   $columns
     *
     * @return array
     */
    public function getMailinglistUser($mailinglistId, $email, $columns=array())
    {
        if (count($columns) == 0) {
            $columns = array('email', 'firstname', 'infix', 'lastname');
        }

        $result = $this->performRequest(
            'Subscriber_getByEmail',
            $email,
            $columns,
            $mailinglistId
        );

        return $result;
    }

    /**
     * Perform the SOAP request against the E-Ngine webservice
     *
     * @param string $method The method to call
     * @param mixed  ...     Additional parameters
     *
     * @return mixed
     *
     * @throws EngineApiException Converted SoapFault Exception
     */
    protected function performRequest($method) {
        // Perform the SOAP request
        $args = func_get_args();
        // remove method argument
        array_shift($args);

        try {
            if ($this->logger) {
                $this->logger->debug(sprintf("E-Ngine API call: %s -> %s", $method, json_encode($args)));
            }

            $result = call_user_func_array(array($this->getConnection(), $method), $args);
        } catch (\SoapFault $e) {
            if ($this->logger) {
                $this->logger->error(sprintf("E-Ngine API error: %s", $e->getMessage()));
            }
            // Convert to EngineApiException
            throw new EngineApiException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $result;
    }

    /**
     * Get the SOAP connection
     *
     * @return SoapClient The SoapClient connection
     */
    protected function getConnection()
    {
        if ($this->connection === null) {
            // create a connection
            $connection = new \SoapClient(
                $this->config['wsdl'],
                array(
                    //"location" => "http" . (($this->config['secure']) ? 's' : '') . "://" . $this->config["domain"] . $this->config["path"],
                    //"uri" => "http" . (($this->config['secure']) ? 's' : '') . "://" . $this->config["domain"] . $this->config["path"],
                    "login" => $this->config["customer"] . "__" . $this->config["user"],
                    "password" => $this->config["password"],
                    "trace" => $this->config["trace"]
                )
            );

            $this->connection = $connection;

            if ($this->config['mailinglist']) {
                // Select the default mailinglist
                $this->selectMailinglist($this->config['mailinglist']);
            }
        }

        return $this->connection;
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
