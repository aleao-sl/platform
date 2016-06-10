<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Tools;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin;
use Oro\Bundle\EmailBundle\Model\FolderType;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\EmailBundle\Tools\EmailOriginHelper;
use Oro\Bundle\OrganizationBundle\Entity\Organization;

class EmailOriginHelperTest extends \PHPUnit_Framework_TestCase
{
    /** @var EmailOriginHelper */
    protected $emailOriginHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $em;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $emailModel;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $emailOwnerProvider;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $securityFacadeLink;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $securityFacade;

    /** @var  EmailAddressHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $emailAddressHelper;

    protected function setUp()
    {
        $this->em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper->expects(self::any())
            ->method('getEntityManager')
            ->with('OroEmailBundle:Email')
            ->will(self::returnValue($this->em));

        $this->emailModel = $this->getMockBuilder('Oro\Bundle\EmailBundle\Form\Model\Email')
            ->setMethods(['getCampaignOwner'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->emailOwnerProvider = $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\Provider\EmailOwnerProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->securityFacadeLink = $this
            ->getMockBuilder('Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink')
            ->setMethods(['getService'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->securityFacade = $this->getMockBuilder('Oro\Bundle\SecurityBundle\SecurityFacade')
            ->setMethods(['getLoggedUser', 'getOrganization'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->securityFacadeLink->expects($this->any())
            ->method('getService')
            ->will($this->returnValue($this->securityFacade));

        $this->securityFacade->expects($this->any())
            ->method('getOrganization')
            ->will($this->returnValue($this->getTestOrganization()));

        $this->emailAddressHelper = new EmailAddressHelper();


        $this->emailOriginHelper = new EmailOriginHelper(
            $this->doctrineHelper,
            $this->securityFacadeLink,
            $this->emailOwnerProvider,
            $this->emailAddressHelper
        );
    }

    /**
     * @dataProvider findEmailOriginDataProvider
     *
     * @param bool                                               $expected
     * @param \PHPUnit_Framework_MockObject_Matcher_InvokedCount $mailModelExpects
     * @param string                                             $emailOwner
     * @param bool                                               $enableUseUserEmailOrigin
     * @param bool                                               $isOriginsNotEmpty
     * @param \PHPUnit_Framework_MockObject_MockObject           $origin
     * @param \PHPUnit_Framework_MockObject_Matcher_InvokedCount $emailOriginsTimes
     */
    public function testFindEmailOrigin(
        $expected,
        $mailModelExpects,
        $emailOwner,
        $enableUseUserEmailOrigin,
        $isOriginsNotEmpty,
        \PHPUnit_Framework_MockObject_MockObject $origin,
        $emailOriginsTimes
    ) {
        $organization  = $this->getMock('Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface');
        $collection    = new ArrayCollection([$origin]);
        $originName    = 'origin name';
        $campaignOwner = null;

        $origin->expects(self::once())->method('getOrganization')->willReturn($organization);

        if (true === $enableUseUserEmailOrigin && true === $isOriginsNotEmpty) {
            $origin->expects(self::once())->method('isActive')->willReturn($isOriginsNotEmpty);
            $origin->expects(self::once())->method('isSmtpConfigured')->willReturn($isOriginsNotEmpty);
        }

        if ('user' === $emailOwner) {
            $emailOwner = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\User')
                ->setMethods(['getEmailOrigins', 'addEmailOrigin'])
                ->disableOriginalConstructor()
                ->getMock();
            $emailOwner
                ->expects($emailOriginsTimes)
                ->method('getEmailOrigins')
                ->willReturn($collection);

            if (false === $enableUseUserEmailOrigin) {
                $emailOwner->expects(self::once())->method('addEmailOrigin');
            }
        } elseif ('emailBox' === $emailOwner) {
            $emailOwner = $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\Mailbox')
                ->setMethods(['getOrigin'])
                ->disableOriginalConstructor()
                ->getMock();

            if (true === $isOriginsNotEmpty) {
                $emailOwner
                    ->expects($emailOriginsTimes)
                    ->method('getOrigin')
                    ->willReturn($collection);
            } else {
                $emailOwner
                    ->expects($emailOriginsTimes)
                    ->method('getOrigin')
                    ->willReturn(null);

                $campaignOwner = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\User')
                    ->setMethods(['getEmailOrigins', 'addEmailOrigin'])
                    ->disableOriginalConstructor()
                    ->getMock();

                $campaignOwner
                    ->expects(self::any())
                    ->method('getEmailOrigins')
                    ->willReturn($collection);
            }
        }

        $this->emailOriginHelper->setEmailModel($this->emailModel);

        $origin = $this->emailOriginHelper
            ->findEmailOrigin($emailOwner, $organization, $originName, $enableUseUserEmailOrigin);

        if (!$expected) {
            self::assertNull($origin);
        } else {
            self::assertInstanceOf($expected, $origin);
        }
    }

    /**
     * @return array
     */
    public function findEmailOriginDataProvider()
    {
        $emailOwnerMailBox = $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\Mailbox')
            ->disableOriginalConstructor()
            ->getMock();

        $userEmailOrigin = $this->getMockBuilder('Oro\Bundle\ImapBundle\Entity\UserEmailOrigin')
            ->disableOriginalConstructor()
            ->getMock();

        $internalEmailOrigin = $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin')
            ->disableOriginalConstructor()
            ->getMock();

        return [
            '1. EmailOwner as User with enableUseUserEmailOrigin and origin is not empty'            => [
                'expected'                 => 'Oro\Bundle\ImapBundle\Entity\UserEmailOrigin',
                'mailModelExpects'         => self::never(),
                'emailOwner'               => 'user',
                'enableUseUserEmailOrigin' => true,
                'isUserOriginsNotEmpty'    => true,
                'origin'                   => $userEmailOrigin,
                'emailOriginsTimes'        => self::once(),
            ],
            '2. EmailOwner as User with enableUseUserEmailOrigin and origin is empty'                => [
                'expected'                 => 'Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin',
                'mailModelExpects'         => self::never(),
                'emailOwner'               => 'user',
                'enableUseUserEmailOrigin' => true,
                'isUserOriginsNotEmpty'    => false,
                'origin'                   => $internalEmailOrigin,
                'emailOriginsTimes'        => self::exactly(2),
            ],
            '3. EmailOwner as User without enableUseUserEmailOrigin and origin is not empty'         => [
                'expected'                 => 'Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin',
                'mailModelExpects'         => self::never(),
                'emailOwner'               => 'user',
                'enableUseUserEmailOrigin' => false,
                'isUserOriginsNotEmpty'    => true,
                'origin'                   => $userEmailOrigin,
                'emailOriginsTimes'        => self::once(),
            ],
            '4. EmailOwner as User without enableUseUserEmailOrigin and origin is empty'             => [
                'expected'                 => 'Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin',
                'mailModelExpects'         => self::never(),
                'emailOwner'               => 'user',
                'enableUseUserEmailOrigin' => false,
                'isUserOriginsNotEmpty'    => false,
                'origin'                   => $userEmailOrigin,
                'emailOriginsTimes'        => self::once(),
            ],
            '5. EmailOwner as Mailbox origin is not empty and enableUseUserEmailOrigin is empty'     => [
                'expected'                 => 'Doctrine\Common\Collections\ArrayCollection',
                'mailModelExpects'         => self::never(),
                'emailOwner'               => 'emailBox',
                'enableUseUserEmailOrigin' => false,
                'isUserOriginsNotEmpty'    => true,
                'origin'                   => $emailOwnerMailBox,
                'emailOriginsTimes'        => self::once(),
            ],
            '6. EmailOwner as Mailbox origin is not empty and enableUseUserEmailOrigin is not empty' => [
                'expected'                 => 'Doctrine\Common\Collections\ArrayCollection',
                'mailModelExpects'         => self::never(),
                'emailOwner'               => 'emailBox',
                'enableUseUserEmailOrigin' => true,
                'isUserOriginsNotEmpty'    => true,
                'origin'                   => $emailOwnerMailBox,
                'emailOriginsTimes'        => self::once(),
            ],
            '7. EmailOwner as Mailbox origin is empty and enableUseUserEmailOrigin is empty'         => [
                'expected'                 => null,
                'mailModelExpects'         => self::once(),
                'emailOwner'               => 'emailBox',
                'enableUseUserEmailOrigin' => false,
                'isUserOriginsNotEmpty'    => false,
                'origin'                   => $emailOwnerMailBox,
                'emailOriginsTimes'        => self::once(),
            ],
            '8. EmailOwner as Mailbox origin is not empty and enableUseUserEmailOrigin is empty'     => [
                'expected'                 => null,
                'mailModelExpects'         => self::once(),
                'emailOwner'               => 'emailBox',
                'enableUseUserEmailOrigin' => true,
                'isUserOriginsNotEmpty'    => false,
                'origin'                   => $emailOwnerMailBox,
                'emailOriginsTimes'        => self::once(),
            ],
        ];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getEmailOwnerMailBoxMock()
    {
        return $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\Mailbox')
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getUserEmailOriginMock()
    {
        return $this->getMockBuilder('Oro\Bundle\ImapBundle\Entity\UserEmailOrigin')
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getInternalEmailOriginMock()
    {
        return $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin')
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    protected function getTestOrganization()
    {
        $organization = new Organization();
        $organization->setId(1);

        return $organization;
    }

    protected function getTestOrigin()
    {
        $outboxFolder = new EmailFolder();
        $outboxFolder
            ->setType(FolderType::SENT)
            ->setName(FolderType::SENT)
            ->setFullName(FolderType::SENT);

        $origin = new InternalEmailOrigin();
        $origin
            ->setName('BAP_User_1')
            ->addFolder($outboxFolder)
            ->setOwner($this->getTestUser())
            ->setOrganization($this->getTestOrganization());

        return $origin;
    }

}
