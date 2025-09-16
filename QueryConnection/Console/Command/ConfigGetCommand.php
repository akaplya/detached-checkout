<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\QueryConnection\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigGetCommand extends Command
{
    private const PATH_OPTION = 'path';
    private const SCOPE_OPTION = 'scope';
    private const SCOPE_ID_OPTION = 'scope-id';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $name
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        string $name = null
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('comopt:config:get')
            ->setDescription('Get Commerce Optimizer configuration values')
            ->addOption(
                self::PATH_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'Configuration path (e.g., comopt/settings/aco/connection/base_uri). If not provided, shows all ACO connection settings.'
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
        $path = $input->getOption(self::PATH_OPTION);
        $scope = $input->getOption(self::SCOPE_OPTION);
        $scopeId = (int)$input->getOption(self::SCOPE_ID_OPTION);

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
            if ($path) {
                // Validate path starts with comopt
                if (!str_starts_with($path, 'comopt/')) {
                    $output->writeln('<error>Path must start with "comopt/"</error>');
                    return Command::FAILURE;
                }

                $value = $this->scopeConfig->getValue($path, $scope, $scopeId);
                $output->writeln(sprintf('<info>%s = %s</info>', $path, $value ?: '(empty)'));
            } else {
                // Show all ACO connection settings
                $settings = [
                    'comopt/settings/aco/connection/base_uri' => 'Base URI',
                    'comopt/settings/aco/connection/ac_channel_id' => 'AC Channel ID',
                    'comopt/settings/aco/connection/ac_environment_id' => 'AC Environment ID',
                    'comopt/settings/aco/connection/ac_price_book_id' => 'AC Price Book ID',
                    'comopt/settings/aco/connection/ac_scope_locale' => 'AC Scope Locale'
                ];

                $output->writeln('<info>ACO Connection Settings:</info>');
                $output->writeln('');

                foreach ($settings as $configPath => $label) {
                    $value = $this->scopeConfig->getValue($configPath, $scope, $scopeId);
                    $output->writeln(sprintf('  %s: %s', $label, $value ?: '(empty)'));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to get configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}

