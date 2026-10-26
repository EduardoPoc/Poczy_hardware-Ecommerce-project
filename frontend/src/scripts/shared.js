const searchInput = document.getElementById('product-search');
const clearSearchButton = document.querySelector('.search-clear');

function updateSearchClearButton() {
  clearSearchButton.hidden = searchInput.value.length === 0;
}

searchInput.addEventListener('input', updateSearchClearButton);
clearSearchButton.addEventListener('click', () => {
  searchInput.value = '';
  searchInput.dispatchEvent(new Event('input', { bubbles: true }));
  searchInput.focus();
});
document.addEventListener('DOMContentLoaded', updateSearchClearButton);
window.addEventListener('pageshow', updateSearchClearButton);

const storeTabs = Array.from(document.querySelectorAll('[data-store-tab]'));

function selectStoreTab(selectedTab) {
  storeTabs.forEach(tab => {
    const active = tab === selectedTab;
    tab.setAttribute('aria-selected', String(active));
    tab.tabIndex = active ? 0 : -1;
    document.getElementById(tab.dataset.storeTab).hidden = !active;
  });
}

storeTabs.forEach((tab, index) => {
  tab.addEventListener('click', () => selectStoreTab(tab));
  tab.addEventListener('keydown', event => {
    let nextIndex;
    if (event.key === 'ArrowRight') nextIndex = (index + 1) % storeTabs.length;
    else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + storeTabs.length) % storeTabs.length;
    else if (event.key === 'Home') nextIndex = 0;
    else if (event.key === 'End') nextIndex = storeTabs.length - 1;
    else return;
    event.preventDefault();
    selectStoreTab(storeTabs[nextIndex]);
    storeTabs[nextIndex].focus();
  });
});
