<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerInventoryItem;
use App\Models\LedgerShipAsset;
use App\Models\LedgerTrade;
use App\Models\LedgerTransferRequest;
use App\Models\LedgerTransaction;
use App\Models\Squadron;
use App\Models\User;
use App\Models\WipeCycle;

class LedgerService
{
    public function __construct(
        protected LedgerCycleService $cycles,
        protected LedgerReadModel $readModel,
        protected LedgerWriteService $writes,
        protected LedgerTransferService $transfers,
    ) {}

    public function currentWipeCycle(): WipeCycle
    {
        return $this->cycles->currentWipeCycle();
    }

    public function defaultAccountFor(User $user): LedgerAccount
    {
        return $this->cycles->defaultAccountFor($user);
    }

    public function defaultSquadronAccountFor(Squadron $squadron, User $actor): LedgerAccount
    {
        return $this->cycles->defaultSquadronAccountFor($squadron, $actor);
    }

    public function defaultOrgAccountFor(User $actor): LedgerAccount
    {
        return $this->cycles->defaultOrgAccountFor($actor);
    }

    public function buildPageData(User $user, string $wipeFilter = 'current'): array
    {
        return $this->readModel->buildPageData($user, $wipeFilter);
    }

    public function buildSquadronPageData(Squadron $squadron, string $wipeFilter = 'current', ?User $viewer = null): array
    {
        return $this->readModel->buildSquadronPageData($squadron, $wipeFilter, $viewer);
    }

    public function buildOrganizationPageData(User $actor, string $wipeFilter = 'current'): array
    {
        return $this->readModel->buildOrganizationPageData($actor, $wipeFilter);
    }

    public function buildAdminData(): array
    {
        return $this->readModel->buildAdminData();
    }

    public function createTransaction(User $actor, User $owner, array $data): LedgerTransaction
    {
        return $this->writes->createTransaction($actor, $owner, $data);
    }

    public function createTrade(User $actor, User $owner, array $data): LedgerTrade
    {
        return $this->writes->createTrade($actor, $owner, $data);
    }

    public function createInventoryItem(User $actor, User $owner, array $data): LedgerInventoryItem
    {
        return $this->writes->createInventoryItem($actor, $owner, $data);
    }

    public function createShipAsset(User $actor, User $owner, array $data): LedgerShipAsset
    {
        return $this->writes->createShipAsset($actor, $owner, $data);
    }

    public function updateTransaction(User $actor, User $owner, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        return $this->writes->updateTransaction($actor, $owner, $transaction, $data);
    }

    public function deleteTransaction(User $actor, User $owner, LedgerTransaction $transaction): void
    {
        $this->writes->deleteTransaction($actor, $owner, $transaction);
    }

    public function updateTrade(User $actor, User $owner, LedgerTrade $trade, array $data): LedgerTrade
    {
        return $this->writes->updateTrade($actor, $owner, $trade, $data);
    }

    public function deleteTrade(User $actor, User $owner, LedgerTrade $trade): void
    {
        $this->writes->deleteTrade($actor, $owner, $trade);
    }

    public function updateInventoryItem(User $actor, User $owner, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        return $this->writes->updateInventoryItem($actor, $owner, $item, $data);
    }

    public function deleteInventoryItem(User $actor, User $owner, LedgerInventoryItem $item): void
    {
        $this->writes->deleteInventoryItem($actor, $owner, $item);
    }

    public function updateShipAsset(User $actor, User $owner, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        return $this->writes->updateShipAsset($actor, $owner, $asset, $data);
    }

    public function deleteShipAsset(User $actor, User $owner, LedgerShipAsset $asset): void
    {
        $this->writes->deleteShipAsset($actor, $owner, $asset);
    }

    public function createSquadronTransaction(User $actor, Squadron $squadron, array $data): LedgerTransaction
    {
        return $this->writes->createSquadronTransaction($actor, $squadron, $data);
    }

    public function updateSquadronTransaction(User $actor, Squadron $squadron, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        return $this->writes->updateSquadronTransaction($actor, $squadron, $transaction, $data);
    }

    public function deleteSquadronTransaction(User $actor, Squadron $squadron, LedgerTransaction $transaction): void
    {
        $this->writes->deleteSquadronTransaction($actor, $squadron, $transaction);
    }

    public function createSquadronTrade(User $actor, Squadron $squadron, array $data): LedgerTrade
    {
        return $this->writes->createSquadronTrade($actor, $squadron, $data);
    }

    public function updateSquadronTrade(User $actor, Squadron $squadron, LedgerTrade $trade, array $data): LedgerTrade
    {
        return $this->writes->updateSquadronTrade($actor, $squadron, $trade, $data);
    }

    public function deleteSquadronTrade(User $actor, Squadron $squadron, LedgerTrade $trade): void
    {
        $this->writes->deleteSquadronTrade($actor, $squadron, $trade);
    }

    public function createSquadronInventoryItem(User $actor, Squadron $squadron, array $data): LedgerInventoryItem
    {
        return $this->writes->createSquadronInventoryItem($actor, $squadron, $data);
    }

    public function updateSquadronInventoryItem(User $actor, Squadron $squadron, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        return $this->writes->updateSquadronInventoryItem($actor, $squadron, $item, $data);
    }

    public function deleteSquadronInventoryItem(User $actor, Squadron $squadron, LedgerInventoryItem $item): void
    {
        $this->writes->deleteSquadronInventoryItem($actor, $squadron, $item);
    }

    public function createSquadronShipAsset(User $actor, Squadron $squadron, array $data): LedgerShipAsset
    {
        return $this->writes->createSquadronShipAsset($actor, $squadron, $data);
    }

    public function updateSquadronShipAsset(User $actor, Squadron $squadron, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        return $this->writes->updateSquadronShipAsset($actor, $squadron, $asset, $data);
    }

    public function deleteSquadronShipAsset(User $actor, Squadron $squadron, LedgerShipAsset $asset): void
    {
        $this->writes->deleteSquadronShipAsset($actor, $squadron, $asset);
    }

    public function createOrgTransaction(User $actor, array $data): LedgerTransaction
    {
        return $this->writes->createOrgTransaction($actor, $data);
    }

    public function updateOrgTransaction(User $actor, LedgerTransaction $transaction, array $data): LedgerTransaction
    {
        return $this->writes->updateOrgTransaction($actor, $transaction, $data);
    }

    public function deleteOrgTransaction(User $actor, LedgerTransaction $transaction): void
    {
        $this->writes->deleteOrgTransaction($actor, $transaction);
    }

    public function createOrgTrade(User $actor, array $data): LedgerTrade
    {
        return $this->writes->createOrgTrade($actor, $data);
    }

    public function updateOrgTrade(User $actor, LedgerTrade $trade, array $data): LedgerTrade
    {
        return $this->writes->updateOrgTrade($actor, $trade, $data);
    }

    public function deleteOrgTrade(User $actor, LedgerTrade $trade): void
    {
        $this->writes->deleteOrgTrade($actor, $trade);
    }

    public function createOrgInventoryItem(User $actor, array $data): LedgerInventoryItem
    {
        return $this->writes->createOrgInventoryItem($actor, $data);
    }

    public function updateOrgInventoryItem(User $actor, LedgerInventoryItem $item, array $data): LedgerInventoryItem
    {
        return $this->writes->updateOrgInventoryItem($actor, $item, $data);
    }

    public function deleteOrgInventoryItem(User $actor, LedgerInventoryItem $item): void
    {
        $this->writes->deleteOrgInventoryItem($actor, $item);
    }

    public function createOrgShipAsset(User $actor, array $data): LedgerShipAsset
    {
        return $this->writes->createOrgShipAsset($actor, $data);
    }

    public function updateOrgShipAsset(User $actor, LedgerShipAsset $asset, array $data): LedgerShipAsset
    {
        return $this->writes->updateOrgShipAsset($actor, $asset, $data);
    }

    public function deleteOrgShipAsset(User $actor, LedgerShipAsset $asset): void
    {
        $this->writes->deleteOrgShipAsset($actor, $asset);
    }

    public function transferFundsFromPersonal(User $actor, array $data): array
    {
        return $this->transfers->transferFundsFromPersonal($actor, $data);
    }

    public function transferFundsFromSquadron(User $actor, Squadron $squadron, array $data): array
    {
        return $this->transfers->transferFundsFromSquadron($actor, $squadron, $data);
    }

    public function transferFundsFromOrganization(User $actor, array $data): array
    {
        return $this->transfers->transferFundsFromOrganization($actor, $data);
    }

    public function transferInventoryFromPersonal(User $actor, array $data): array
    {
        return $this->transfers->transferInventoryFromPersonal($actor, $data);
    }

    public function transferInventoryFromSquadron(User $actor, Squadron $squadron, array $data): array
    {
        return $this->transfers->transferInventoryFromSquadron($actor, $squadron, $data);
    }

    public function transferInventoryFromOrganization(User $actor, array $data): array
    {
        return $this->transfers->transferInventoryFromOrganization($actor, $data);
    }

    public function approvePendingFundTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest): array
    {
        return $this->transfers->approvePendingFundTransferForPersonal($actor, $transferRequest);
    }

    public function rejectPendingFundTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->transfers->rejectPendingFundTransferForPersonal($actor, $transferRequest, $data);
    }

    public function approvePendingInventoryTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest): array
    {
        return $this->transfers->approvePendingInventoryTransferForPersonal($actor, $transferRequest);
    }

    public function rejectPendingInventoryTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->transfers->rejectPendingInventoryTransferForPersonal($actor, $transferRequest, $data);
    }

    public function approvePendingFundTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest): array
    {
        return $this->transfers->approvePendingFundTransferForSquadron($actor, $squadron, $transferRequest);
    }

    public function rejectPendingFundTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->transfers->rejectPendingFundTransferForSquadron($actor, $squadron, $transferRequest, $data);
    }

    public function approvePendingInventoryTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest): array
    {
        return $this->transfers->approvePendingInventoryTransferForSquadron($actor, $squadron, $transferRequest);
    }

    public function rejectPendingInventoryTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->transfers->rejectPendingInventoryTransferForSquadron($actor, $squadron, $transferRequest, $data);
    }

    public function approvePendingInventoryTransferForOrganization(User $actor, LedgerTransferRequest $transferRequest): array
    {
        return $this->transfers->approvePendingInventoryTransferForOrganization($actor, $transferRequest);
    }

    public function rejectPendingInventoryTransferForOrganization(User $actor, LedgerTransferRequest $transferRequest, array $data = []): LedgerTransferRequest
    {
        return $this->transfers->rejectPendingInventoryTransferForOrganization($actor, $transferRequest, $data);
    }

    public function reverseFundTransferForPersonal(User $actor, LedgerTransferRequest $transferRequest, array $data = []): array
    {
        return $this->transfers->reverseFundTransferForPersonal($actor, $transferRequest, $data);
    }

    public function reverseFundTransferForSquadron(User $actor, Squadron $squadron, LedgerTransferRequest $transferRequest, array $data = []): array
    {
        return $this->transfers->reverseFundTransferForSquadron($actor, $squadron, $transferRequest, $data);
    }

    public function reverseFundTransferForOrganization(User $actor, LedgerTransferRequest $transferRequest, array $data = []): array
    {
        return $this->transfers->reverseFundTransferForOrganization($actor, $transferRequest, $data);
    }

    public function createWipeCycle(User $actor, array $data): WipeCycle
    {
        return $this->cycles->createWipeCycle($actor, $data);
    }

    public function setCurrentWipeCycle(User $actor, WipeCycle $wipeCycle): WipeCycle
    {
        return $this->cycles->setCurrentWipeCycle($actor, $wipeCycle);
    }

    public function closeWipeCycle(User $actor, WipeCycle $wipeCycle): WipeCycle
    {
        return $this->cycles->closeWipeCycle($actor, $wipeCycle);
    }

    public function updateCurrentWipeCycle(User $actor, WipeCycle $wipeCycle, array $data): WipeCycle
    {
        return $this->cycles->updateCurrentWipeCycle($actor, $wipeCycle, $data);
    }
}
