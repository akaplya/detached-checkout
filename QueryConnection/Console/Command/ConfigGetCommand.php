<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
namespace CommerceOptimizer\QueryConnection\Console\Command;

use CommerceOptimizer\QueryConnection\Model\AcoConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigGetCommand extends Command
{
    private const KEY_OPTION = 'key';
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
        ?string $name = null
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
                self::KEY_OPTION,
                'k',
                InputOption::VALUE_OPTIONAL,
                'Configuration key (e.g., base_uri). If not provided, shows all ACO connection settings.'
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
        $path = $input->getOption(self::KEY_OPTION);
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
                    AcoConfig::XML_PATH_BASE_URI => 'Base URI',
                    AcoConfig::XML_PATH_AC_CHANNEL_ID => 'AC Channel ID',
                    AcoConfig::XML_PATH_AC_ENVIRONMENT_ID => 'AC Environment ID',
                    AcoConfig::XML_PATH_AC_PRICE_BOOK_ID => 'AC Price Book ID',
                    AcoConfig::XML_PATH_AC_SCOPE_LOCALE => 'AC Scope Locale'
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
