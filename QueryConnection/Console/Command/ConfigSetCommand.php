<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\QueryConnection\Console\Command;

use CommerceOptimizer\QueryConnection\Model\AcoConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSetCommand extends Command
{
    private const KEY_OPTION = 'key';
    private const VALUE_OPTION = 'value';
    private const SCOPE_OPTION = 'scope';
    private const SCOPE_ID_OPTION = 'scope-id';

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param WriterInterface $configWriter
     * @param string|null $name
     */
    public function __construct(
        WriterInterface $configWriter,
        string $name = null
    ) {
        $this->configWriter = $configWriter;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('comopt:config:set')
            ->setDescription('Set Commerce Optimizer configuration values')
            ->addOption(
                self::KEY_OPTION,
                'p',
                InputOption::VALUE_REQUIRED,
                'Configuration settings (e.g., base_uri)'
            )
            ->addOption(
                self::VALUE_OPTION,
                null,
                InputOption::VALUE_REQUIRED,
                'Configuration value'
            )
            ->addOption(
                self::SCOPE_OPTION,
                's',
                InputOption::VALUE_OPTIONAL,
                'Configuration scope (default, website, store)',
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            )
            ->addOption(
                self::SCOPE_ID_OPTION,
                'i',
                InputOption::VALUE_OPTIONAL,
                'Configuration scope ID (required for website/store scope)',
                0
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getOption(self::KEY_OPTION);
        $value = $input->getOption(self::VALUE_OPTION);
        $scope = $input->getOption(self::SCOPE_OPTION);
        $scopeId = (int)$input->getOption(self::SCOPE_ID_OPTION);

        if (!$key || !$value) {
            $output->writeln('<error>Both path and value are required</error>');
            return Command::FAILURE;
        }
        if (!in_array($key, [AcoConfig::BASE_URI, AcoConfig::CHANNEL_ID, AcoConfig::ENVIRONMENT_ID, AcoConfig::PRICE_BOOK_ID, AcoConfig::SCOPE_LOCALE])) {
            $output->writeln('<error>Invalid settings</error>');
            return Command::FAILURE;
        }
        // Validate scope
        $allowedScopes = [
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            ScopeInterface::SCOPE_WEBSITE,
            ScopeInterface::SCOPE_STORE
        ];
        if (!in_array($scope, $allowedScopes)) {
            $output->writeln('<error>Invalid scope. Allowed values: default, website, store</error>');
            return Command::FAILURE;
        }

        try {
            $this->configWriter->save(AcoConfig::$configMap[$key], $value, $scope, $scopeId);
            $output->writeln(
                sprintf(
                    '<info>Configuration saved: %s = %s (scope: %s, scope-id: %d)</info>',
                    AcoConfig::$configMap[$key],
                    $value,
                    $scope,
                    $scopeId
                )
            );
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to save configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
