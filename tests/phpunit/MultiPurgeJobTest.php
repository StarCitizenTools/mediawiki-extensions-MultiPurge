<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MultiPurge\Tests;

use ArrayObject;
use JobQueueGroup;
use MediaWiki\Extension\MultiPurge\MultiPurgeJob;
use MediaWiki\Extension\MultiPurge\Services\Cloudflare;
use MediaWiki\Extension\MultiPurge\Services\Varnish;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Http\MultiHttpClient;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group MultiPurge
 */
class MultiPurgeJobTest extends MediaWikiIntegrationTestCase {

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob
	 * @return void
	 */
	public function testConstructor() {
		$job = new MultiPurgeJob( [] );

		$this->assertInstanceOf( MultiPurgeJob::class, $job );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getServiceOrder
	 * @return void
	 */
	public function testServiceOrderOne() {
		$this->overrideConfigValues( [
			'MultiPurgeEnabledServices' => [
				Varnish::class,
			]
		] );

		$this->assertCount( 1, MultiPurgeJob::getServiceOrder() );
		$this->assertEquals( Varnish::class, MultiPurgeJob::getServiceOrder()[0] );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getServiceOrder
	 * @return void
	 */
	public function testServiceOrderTwo() {
		$this->overrideConfigValues( [
			'MultiPurgeEnabledServices' => [
				Varnish::class,
				Cloudflare::class,
			]
		] );

		$this->assertCount( 2, MultiPurgeJob::getServiceOrder() );
		$this->assertEquals( [ Varnish::class, Cloudflare::class ], MultiPurgeJob::getServiceOrder() );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getServiceOrder
	 * @return void
	 */
	public function testServiceOrderTwoReverse() {
		$this->overrideConfigValues( [
			'MultiPurgeEnabledServices' => [
				Cloudflare::class,
				Varnish::class,
			]
		] );

		$this->assertCount( 2, MultiPurgeJob::getServiceOrder() );
		$this->assertEquals( [ Cloudflare::class, Varnish::class ], MultiPurgeJob::getServiceOrder() );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::run
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getPurgeService
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getServiceOrder
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::normalizeServiceName
	 * @covers \MediaWiki\Extension\MultiPurge\Services\Varnish::getPurgeRequest
	 * @covers \MediaWiki\Extension\MultiPurge\Services\Varnish::buildUrl
	 * @covers \MediaWiki\Extension\MultiPurge\Services\Cloudflare::getPurgeRequest
	 * @return void
	 * @throws \Exception
	 */
	public function testRun() {
		$this->overrideConfigValues( [
			'MultiPurgeEnabledServices' => [
				Cloudflare::class,
				Varnish::class,
			],
			'MultiPurgeVarnishServers' => [
				'127.0.0.1',
			],
		] );

		$multiMock = $this->getMockBuilder( MultiHttpClient::class )->disableOriginalConstructor()->getMock();
		$multiMock->expects( $this->once() )->method( 'runMulti' )->willReturn( [
			[ 'response' => [ 200, null, null, null, null ] ],
		] );

		$httpMock = $this->getMockBuilder( HttpRequestFactory::class )->disableOriginalConstructor()->getMock();
		$httpMock->expects( $this->once() )->method( 'createMultiClient' )->willReturn( $multiMock );

		$this->getServiceContainer()->redefineService( 'HttpRequestFactory', fn() => $httpMock );

		$job = new MultiPurgeJob( [
			'urls' => [
				'http://localhost',
			],
		] );

		$this->assertTrue( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::run
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getPurgeService
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getServiceOrder
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::normalizeServiceName
	 * @covers \MediaWiki\Extension\MultiPurge\Services\Varnish::getPurgeRequest
	 * @covers \MediaWiki\Extension\MultiPurge\Services\Cloudflare::getPurgeRequest
	 * @return void
	 * @throws \Exception
	 */
	public function testRunFalse() {
		$this->overrideConfigValues( [
			'MultiPurgeEnabledServices' => [
				Cloudflare::class,
				Varnish::class,
			]
		] );

		$multiMock = $this->getMockBuilder( MultiHttpClient::class )->disableOriginalConstructor()->getMock();
		$multiMock->expects( $this->once() )->method( 'runMulti' )->willReturn( [
			[ 'response' => [ 500, null, null, null, null ] ],
		] );

		$httpMock = $this->getMockBuilder( HttpRequestFactory::class )->disableOriginalConstructor()->getMock();
		$httpMock->expects( $this->once() )->method( 'createMultiClient' )->willReturn( $multiMock );

		$this->getServiceContainer()->redefineService( 'HttpRequestFactory', fn() => $httpMock );

		$job = new MultiPurgeJob( [
			'urls' => [
				'http://localhost',
			],
		] );

		$this->assertFalse( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getReleaseTimestamp
	 * @return void
	 */
	public function testGetReleaseTimestamp() {
		$job = new MultiPurgeJob( [ 'jobReleaseTimestamp' => 1600000000 ] );

		$this->assertEquals( 1600000000, $job->getReleaseTimestamp() );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::getReleaseTimestamp
	 * @return void
	 */
	public function testGetCfReleaseTimestamp() {
		$job = new MultiPurgeJob( [
			'jobReleaseTimestamp' => 1600000000,
			'urls' => [
				'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
				'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
				'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
				'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
				'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
			],
			'service' => Cloudflare::class,
		] );

		$this->assertEquals( 1600000012, $job->getReleaseTimestamp() );
	}

	/**
	 * Answers each Cloudflare request with $respond( $request )
	 *
	 * @param callable $respond
	 * @return ArrayObject[] The jobs the run queues, and the requests it sent
	 */
	private function mockCloudflareResponses( callable $respond ): array {
		$this->overrideConfigValues( [
			'MultiPurgeEnabledServices' => [ Cloudflare::class ],
		] );
		ConvertibleTimestamp::setFakeTime( 1700000000 );

		$sent = new ArrayObject();
		$multiMock = $this->getMockBuilder( MultiHttpClient::class )->disableOriginalConstructor()->getMock();
		$multiMock->expects( $this->once() )->method( 'runMulti' )->willReturnCallback(
			static function ( array $requests ) use ( $respond, $sent ) {
				foreach ( $requests as $i => $request ) {
					$sent->append( $request );
					$requests[$i]['response'] = $respond( $request );
				}
				return $requests;
			}
		);
		$httpMock = $this->getMockBuilder( HttpRequestFactory::class )->disableOriginalConstructor()->getMock();
		$httpMock->method( 'createMultiClient' )->willReturn( $multiMock );
		$this->getServiceContainer()->redefineService( 'HttpRequestFactory', static fn () => $httpMock );

		$queued = new ArrayObject();
		$groupMock = $this->createMock( JobQueueGroup::class );
		$groupMock->method( 'lazyPush' )->willReturnCallback( static function ( $jobs ) use ( $queued ) {
			foreach ( is_array( $jobs ) ? $jobs : [ $jobs ] as $job ) {
				$queued->append( $job );
			}
		} );
		$factoryMock = $this->createMock( JobQueueGroupFactory::class );
		$factoryMock->method( 'makeJobQueueGroup' )->willReturn( $groupMock );
		$this->setService( 'JobQueueGroupFactory', $factoryMock );

		return [ $queued, $sent ];
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::run
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::retryRateLimited
	 * @return void
	 */
	public function testRunRetriesOnlyRateLimitedRequests() {
		// Only the request carrying the last URL is rate-limited
		[ $queued, $sent ] = $this->mockCloudflareResponses( static fn ( array $request ) => in_array(
			'https://example.org/150', $request['purgeUrls'], true
		) ? [ 429, null, [ 'retry-after' => '42' ], null, null ] : [ 200, null, [], null, null ] );
		$urls = array_map( static fn ( $i ) => "https://example.org/$i", range( 1, 150 ) );

		$job = new MultiPurgeJob( [ 'urls' => $urls ] );

		$this->assertTrue( $job->run() );
		$this->assertGreaterThan( 1, count( $sent ) );
		$this->assertCount( 1, $queued );
		$params = $queued[0]->getParams();
		$this->assertSame( $sent[count( $sent ) - 1]['purgeUrls'], $params['urls'] );
		$this->assertSame( Cloudflare::class, $params['service'] );
		$this->assertSame( 1, $params['rateLimitRetries'] );
		$this->assertSame( 1700000042, $params['jobReleaseTimestamp'] );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::run
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::retryRateLimited
	 * @return void
	 */
	public function testRunBacksOffWithoutRetryAfter() {
		[ $queued ] = $this->mockCloudflareResponses( static fn () => [ 429, null, [], null, null ] );

		$job = new MultiPurgeJob( [
			'urls' => [ 'https://example.org/1' ],
			'service' => Cloudflare::class,
			'rateLimitRetries' => 2,
		] );

		$this->assertTrue( $job->run() );
		$this->assertCount( 1, $queued );
		$this->assertSame( 3, $queued[0]->getParams()['rateLimitRetries'] );
		$this->assertSame( 1700000240, $queued[0]->getParams()['jobReleaseTimestamp'] );
	}

	/**
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::run
	 * @covers \MediaWiki\Extension\MultiPurge\MultiPurgeJob::retryRateLimited
	 * @return void
	 */
	public function testRunGivesUpAfterMaxRateLimitRetries() {
		[ $queued ] = $this->mockCloudflareResponses(
			static fn () => [ 429, null, [ 'retry-after' => '42' ], null, null ]
		);
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'error' );
		$this->setLogger( 'MultiPurge', $logger );

		$job = new MultiPurgeJob( [
			'urls' => [ 'https://example.org/1' ],
			'service' => Cloudflare::class,
			'rateLimitRetries' => 5,
		] );

		$this->assertTrue( $job->run() );
		$this->assertCount( 0, $queued );
	}
}
