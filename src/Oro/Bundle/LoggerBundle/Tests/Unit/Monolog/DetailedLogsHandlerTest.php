<?php

namespace Oro\Bundle\LoggerBundle\Tests\Unit\Monolog;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\LoggerBundle\Monolog\DetailedLogsHandler;

class DetailedLogsHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $config;

    /**
     * @var HandlerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $nestedHandler;

    /**
     * @var DetailedLogsHandler
     */
    protected $handler;

    protected function setUp()
    {
        $this->config = $this->getMock(ConfigManager::class, [], [], '', false);
        $this->nestedHandler = $this->getMock(HandlerInterface::class, [], [], '', false);

        $container = $this->getMock(ContainerInterface::class, [], [], '', false);
        $container->expects($this->any())
            ->method('get')
            ->with('oro_config.user')
            ->willReturn($this->config);

        $this->handler = new DetailedLogsHandler();
        $this->handler->setHandler($this->nestedHandler);
        $this->handler->setContainer($container);
    }

    public function testIsHandling()
    {
        $this->assertTrue($this->handler->isHandling([]));
    }

    public function testNoRecordsArePassedToNestedHandlerWhenEndTimestampCheckFails()
    {
        $this->nestedHandler->expects($this->never())->method('handleBatch');

        $this->handler->close();
    }

    /**
     * @dataProvider closeProvider
     *
     * @param array $expectedRecords
     * @param null|string $detailedLogsLevelValue
     */
    public function testClose(array $expectedRecords, $detailedLogsLevelValue)
    {
        $this->config->expects($this->at(0))
            ->method('get')
            ->with('oro_logger.detailed_logs_end_timestamp')
            ->willReturn(time() + 3600);

        $this->config->expects($this->at(1))
            ->method('get')
            ->with('oro_logger.detailed_logs_level')
            ->willReturn($detailedLogsLevelValue);

        $this->nestedHandler->expects($this->once())
            ->method('handleBatch')
            ->with($expectedRecords);

        $this->handler->handle(['level' => Logger::DEBUG]);
        $this->handler->handle(['level' => Logger::INFO]);
        $this->handler->handle(['level' => Logger::WARNING]);
        $this->handler->handle(['level' => Logger::ERROR]);
        $this->handler->handle(['level' => Logger::CRITICAL]);

        $this->handler->close();
    }

    public function closeProvider()
    {
        return [
            'based on value from config' => [
                'expectedRecords' => [
                    2 => ['level' => Logger::WARNING],
                    3 => ['level' => Logger::ERROR],
                    4 => ['level' => Logger::CRITICAL],
                ],
                'detailedLogsLevelValue' => 'warning',
            ],
            'based on default value' => [
                'expectedRecords' => [
                    1 => ['level' => Logger::INFO],
                    2 => ['level' => Logger::WARNING],
                    3 => ['level' => Logger::ERROR],
                    4 => ['level' => Logger::CRITICAL],
                ],
                'detailedLogsLevelValue' => null,
            ]
        ];
    }
}