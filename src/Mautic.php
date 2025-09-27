<?php

namespace Triibo\Mautic;

use GrahamCampbell\Manager\AbstractManager;
use Illuminate\Contracts\Config\Repository;
use Triibo\Mautic\Factories\MauticFactory;
use Triibo\Mautic\Models\MauticConsumer;

class Mautic extends AbstractManager
{
    /**
     * The factory instance.
     *
     * @var     Factory
     */
    protected $factory;

    /**
     * Create a new Mautic manager instance.
     *
     * @param   Repository      $config
     * @param   MauticFactory   $factory
     * @return  void
     */
    public function __construct(Repository $config, MauticFactory $factory)
    {
        parent::__construct($config);

        $this->factory = $factory;
    }

    /**
     * Create the connection instance.
     *
     * @param   array   $config
     * @return  mixed
     */
    protected function createConnection($config): object
    {
        return $this->factory->make($config);
    }

    /**
     * Get the configuration name.
     *
     * @return  string
     */
    protected function getConfigName(): string
    {
        return "mautic";
    }

    /**
     * Get the factory instance.
     *
     * @return  MauticFactory
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Makes a request to Mautic.
     *
     * @param   string|null     $method
     * @param   string|null     $endpoints
     * @param   array|null      $body
     * @return  mixed
     */
    public function request(
        ?string $method = null,
        ?string $endpoints = null,
        ?array $body = null,
        ?bool $asQueryParams = false
    ) {
        $consumer = MauticConsumer::whereNotNull("id")->orderBy("created_at", "desc")->first();

        if (empty($consumer) || $this->factory->checkExpirationTime($consumer->expires)) {
            $consumer = $this->factory->make(config("mautic.connections.main"));
        }

        return $this->factory->callMautic($method, $endpoints, $body, $consumer->access_token, $asQueryParams);
    }
}
