// Import utils
import testContext from '@utils/testContext';

// Import pages
import statusesPage from '@pages/BO/shopParameters/orderSettings/statuses';
import addOrderReturnStatusPage from '@pages/BO/shopParameters/orderSettings/statuses/returnStatus/add';

import {expect} from 'chai';
import {
  boDashboardPage,
  boLoginPage,
  boOrderSettingsPage,
  type BrowserContext,
  FakerOrderReturnStatus,
  type Page,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_shopParameters_orderSettings_statuses_returnStatuses_CRUDOrderReturnStatus';

/*
Create new order return status
Update order return status
Delete order return status
 */
describe('BO - Shop Parameters - Order Settings - Statuses : CRUD order return status', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let numberOfOrderReturnStatuses: number = 0;

  const tableName: string = 'order_return';
  const createOrderReturnStatusData: FakerOrderReturnStatus = new FakerOrderReturnStatus();
  const editOrderStatusData: FakerOrderReturnStatus = new FakerOrderReturnStatus({
    name: `edit_${createOrderReturnStatusData.name}`,
  });

  // before and after functions
  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
  });

  it('should login in BO', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

    await boLoginPage.goTo(page, global.BO.URL);
    await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

    const pageTitle = await boDashboardPage.getPageTitle(page);
    expect(pageTitle).to.contains(boDashboardPage.pageTitle);
  });

  it('should go to \'Shop Parameters > Order Settings\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToOrderSettingsPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.shopParametersParentLink,
      boDashboardPage.orderSettingsLink,
    );

    const pageTitle = await boOrderSettingsPage.getPageTitle(page);
    expect(pageTitle).to.contains(boOrderSettingsPage.pageTitle);
  });

  it('should go to \'Statuses\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToStatusesPage', baseContext);

    await boOrderSettingsPage.goToStatusesPage(page);

    const pageTitle = await statusesPage.getPageTitle(page);
    expect(pageTitle).to.contains(statusesPage.pageTitle);
  });

  it('should reset all filters and get number of order return statuses', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'resetFilterFirst', baseContext);

    numberOfOrderReturnStatuses = await statusesPage.resetAndGetNumberOfLines(page, tableName);
    expect(numberOfOrderReturnStatuses).to.be.above(0);
  });

  // 1 - Create order return status
  describe('Create order return status', async () => {
    it('should go to add new order return status page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToAddOrderReturnStatusPage', baseContext);

      await statusesPage.goToNewOrderReturnStatusPage(page);

      const pageTitle = await addOrderReturnStatusPage.getPageTitle(page);
      expect(pageTitle).to.eq(addOrderReturnStatusPage.pageTitleCreate);
    });

    it('should create order return status and check result', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'createOrderStatus', baseContext);

      const textResult = await addOrderReturnStatusPage.setOrderReturnStatus(page, createOrderReturnStatusData);
      expect(textResult).to.contains(statusesPage.successfulCreationMessage);

      const numberOfLinesAfterCreation = await statusesPage.getNumberOfElementInGrid(page, tableName);
      expect(numberOfLinesAfterCreation).to.be.equal(numberOfOrderReturnStatuses + 1);
    });
  });

  // 2 - Update order return status
  describe('Update order return status created', async () => {
    it('should filter list by name', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterForUpdate', baseContext);

      await statusesPage.resetFilter(page, tableName);
      await statusesPage.filterTable(
        page,
        tableName,
        'input',
        'name',
        createOrderReturnStatusData.name,
      );

      const textEmail = await statusesPage.getTextColumn(page, tableName, 1, 'name');
      expect(textEmail).to.contains(createOrderReturnStatusData.name);
    });

    it('should go to edit order return status page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToEditOrderReturnStatusPage', baseContext);

      await statusesPage.goToEditPage(page, tableName, 1);

      const pageTitle = await addOrderReturnStatusPage.getPageTitle(page);
      expect(pageTitle).to.contains(addOrderReturnStatusPage.pageTitleEdit(createOrderReturnStatusData.name));
    });

    it('should update order return status', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'updateOrderReturnStatus', baseContext);

      const textResult = await addOrderReturnStatusPage.setOrderReturnStatus(page, editOrderStatusData);
      expect(textResult).to.contains(statusesPage.successfulUpdateMessage);

      const numberOfOrderReturnStatusesAfterUpdate = await statusesPage.resetAndGetNumberOfLines(page, tableName);
      expect(numberOfOrderReturnStatusesAfterUpdate).to.be.equal(numberOfOrderReturnStatuses + 1);
    });
  });

  // 3 - Delete order return status
  describe('Delete order return status', async () => {
    it('should filter list by name', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterToDelete', baseContext);

      await statusesPage.resetFilter(page, tableName);
      await statusesPage.filterTable(
        page,
        tableName,
        'input',
        'name',
        editOrderStatusData.name,
      );

      const textEmail = await statusesPage.getTextColumn(page, tableName, 1, 'name');
      expect(textEmail).to.contains(editOrderStatusData.name);
    });

    it('should delete order return status', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'deleteOrderStatus', baseContext);

      const textResult = await statusesPage.deleteOrderStatus(page, tableName, 1);
      expect(textResult).to.contains(statusesPage.successfulDeleteMessage);

      const numberOfOrderReturnStatusesAfterDelete = await statusesPage.resetAndGetNumberOfLines(page, tableName);
      expect(numberOfOrderReturnStatusesAfterDelete).to.be.equal(numberOfOrderReturnStatuses);
    });
  });
});
