<?php

namespace Joindin\Api\Test\Model;

use Joindin\Api\Model\TalkMapper;
use Joindin\Api\Request;
use Joindin\Api\Test\Mock\mockPDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class TalkMapperTest extends TestCase
{
    public function testMediaTypesAreAddedCorrectly(): void
    {
        $request = new Request(
            [],
            [
                'REQUEST_URI' => 'http://api.dev.joind.in/v2.1/talks/3?verbose=yes',
                'REQUEST_METHOD' => 'GET'
            ]
        );

        $mockdb = $this->getMockBuilder(mockPDO::class)->getMock();
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->setMethods(["execute", 'fetchAll'])
            ->getMock();

        $stmt->method("execute")->willReturn(true);

        $stmt->method("fetchAll")->willReturn(
            $this->getValidMediaRows()
        );

        $mockdb->method('prepare')
            ->willReturn($stmt);

        $talk_mapper = new TalkMapper($mockdb, $request);
        $talk = [
            [
                'ID' => 3,
            ]
        ];
        $talk = $talk_mapper->addTalkMediaTypes($talk);

        $this->assertSame(
            'https://slideshare.net',
            $talk[0]['slides_link']
        );

        $this->assertSame(
            $this->transformMediaRows($this->getValidMediaRows()),
            $talk[0]['talk_media']
        );
    }

    private function getValidMediaRows(): array
    {
        return [
            [
                'display_name' => "slides_link",
                'url' => "https://slideshare.net",
            ],
            [
                'display_name' => "video_link",
                'url' => "https://youtube.com",
            ]
        ];
    }

    private function transformMediaRows(array $rows): array
    {
        $transformedRows = [];

        foreach ($rows as $row) {
            $transformedRows[] = [$row['display_name'] => $row['url']];
        }

        return $transformedRows;
    }

    public function testThatTalkLinksAreDeletedCorrectly(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->willReturn(true);

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->with('DELETE FROM talk_links WHERE talk_id = :talk_id')
            ->willReturn($stmt);

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertTrue($mapper->removeAllTalkLinks(12));
    }

    public function testThatRemovingTalkFromAllTracksWorksCorrectly(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->willReturn(true);

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->with('DELETE FROM talk_track WHERE talk_id = :talk_id')
            ->willReturn($stmt);

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertTrue($mapper->removeTalkFromAllTracks(12));
    }

    public function testThatSpeakersAreDeletedFromTalkCorrectly(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->willReturn(true);

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->with('DELETE FROM talk_speaker WHERE talk_id = :talk_id')
            ->willReturn($stmt);

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertTrue($mapper->removeAllSpeakersFromTalk(12));
    }

    public function testThatBrokenTalkLinksDeletionRollsBack(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->willReturn(false);

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->with('DELETE FROM talk_links WHERE talk_id = :talk_id')
            ->willReturn($stmt);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertFalse($mapper->delete(12));
    }

    public function testThatBrokenTracksDeletionRollsBack(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->will($this->onConsecutiveCalls(true, false));

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->withConsecutive(
                [$this->equalTo('DELETE FROM talk_links WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_track WHERE talk_id = :talk_id')]
            )
            ->willReturn($stmt);

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertFalse($mapper->delete(12));
    }

    public function testThatBrokenSpeakerDeletionRollsBack(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->will($this->onConsecutiveCalls(true, true, false));

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->withConsecutive(
                [$this->equalTo('DELETE FROM talk_links WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_track WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_speaker WHERE talk_id = :talk_id')]
            )
            ->willReturn($stmt);

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertFalse($mapper->delete(12));
    }

    public function testThatBrokenTalkDeletionRollsBack(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->will($this->onConsecutiveCalls(true, true, true, false));

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->withConsecutive(
                [$this->equalTo('DELETE FROM talk_links WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_track WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_speaker WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talks WHERE ID = :talk_id')]
            )
            ->willReturn($stmt);

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertFalse($mapper->delete(12));
    }

    public function testThatWorkingTalkDeletionCommits(): void
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        $stmt->method('execute')
            ->with(['talk_id' => 12])
            ->will($this->onConsecutiveCalls(true, true, true, true));

        $pdo = $this->getMockBuilder(mockPDO::class)->disableOriginalConstructor()->getMock();
        $pdo->method('prepare')
            ->withConsecutive(
                [$this->equalTo('DELETE FROM talk_links WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_track WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talk_speaker WHERE talk_id = :talk_id')],
                [$this->equalTo('DELETE FROM talks WHERE ID = :talk_id')]
            )
            ->willReturn($stmt);

        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');

        $mapper = new TalkMapper($pdo, new Request([1], [2]));

        $this->assertTrue($mapper->delete(12));
    }
}
