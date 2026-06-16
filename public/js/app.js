/**
 * LOGG v3.2 - APPLICATION JAVASCRIPT
 * Gestion complète de l'interface
 * 
 * Fonctionnalités:
 * - Édition inline des notes (double-click)
 * - Lazy loading des images
 * - Sticky headers
 * - Validations et mises à jour AJAX
 */

// ═══════════════════════════════════════════════════════════════
// TABLE SORTING
// ═══════════════════════════════════════════════════════════════

/**
 * Initialiser le tri des colonnes
 */
function initTableSorting() {
    console.log('📊 initTableSorting called');
    
    const sortableHeaders = document.querySelectorAll('th.sortable');
    console.log('Found sortable headers:', sortableHeaders.length);
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔄 Sorting by:', this.getAttribute('data-sort'));
            sortTable(this);
        });
    });
}

/**
 * Trier le tableau
 * @param {Element} header - L'header cliqué
 */
function sortTable(header) {
    const sortColumn = header.getAttribute('data-sort');
    const table = header.closest('.results-table')?.querySelector('table') || 
                  document.querySelector('.results-table table');
    
    if (!table) {
        console.error('❌ Table not found');
        return;
    }
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Déterminer l'ordre de tri
    let isAsc = !header.classList.contains('asc-active');
    
    // Réinitialiser les autres colonnes
    table.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('asc-active', 'desc-active');
        th.querySelector('.sort-indicator').classList.remove('asc', 'desc');
    });
    
    // Ajouter la classe à la colonne actuelle
    if (isAsc) {
        header.classList.add('asc-active');
        header.querySelector('.sort-indicator').classList.add('asc');
    } else {
        header.classList.add('desc-active');
        header.querySelector('.sort-indicator').classList.add('desc');
    }
    
    console.log('Sorting order:', isAsc ? 'ASC' : 'DESC');
    
    // Trier les rows
    rows.sort((rowA, rowB) => {
        let cellA, cellB;
        
        // Déterminer le numéro de la colonne en fonction du data-sort
        let cells = Array.from(header.parentElement.children);
        let colIndex = cells.indexOf(header);
        
        // Utiliser data-sort-value si disponible, sinon utiliser textContent
        cellA = rowA.cells[colIndex]?.getAttribute('data-sort-value') || rowA.cells[colIndex]?.textContent.trim() || '';
        cellB = rowB.cells[colIndex]?.getAttribute('data-sort-value') || rowB.cells[colIndex]?.textContent.trim() || '';
        
        console.log('Comparing:', sortColumn, '|', cellA.substring(0, 20), '|', cellB.substring(0, 20));
        
        // Trier numérique si ce sont des nombres
        let isNumeric = !isNaN(cellA) && !isNaN(cellB) && cellA !== '' && cellB !== '';
        
        let result = 0;
        if (isNumeric) {
            result = parseInt(cellA) - parseInt(cellB);
        } else {
            result = cellA.localeCompare(cellB);
        }
        
        return isAsc ? result : -result;
    });
    
    // Réinsérer les rows triées
    rows.forEach(row => {
        tbody.appendChild(row);
    });
    
    console.log('✅ Table sorted:', sortColumn, isAsc ? 'ASC' : 'DESC');
}

// ═══════════════════════════════════════════════════════════════
// 1. ÉDITION INLINE DES NOTES
// ═══════════════════════════════════════════════════════════════

function initEditableNotes() {
    console.log('🔧 initEditableNotes called');
    
    let noteCells = document.querySelectorAll('.notes-cell');
    console.log('Found .notes-cell:', noteCells.length);
    
    if (noteCells.length === 0) {
        noteCells = document.querySelectorAll('.notes');
        console.log('Fallback .notes found:', noteCells.length);
    }
    
    console.log('📝 Total notes cells to edit:', noteCells.length);
    
    noteCells.forEach((cell, index) => {
        if (!cell.classList.contains('notes-cell')) {
            cell.classList.add('notes-cell');
        }
        
        cell.addEventListener('dblclick', function(e) {
            console.log('🖱️ Double-click detected on notes cell');
            e.stopPropagation();
            editNote(this);
        });
        
        cell.style.cursor = 'pointer';
        cell.style.userSelect = 'none';
        cell.title = 'Double-click to edit notes';
        
        const row = cell.closest('tr');
        if (!cell.getAttribute('data-autoid') && row) {
            const autoID = row.getAttribute('data-autoid');
            if (autoID) cell.setAttribute('data-autoid', autoID);
        }
        if (!cell.getAttribute('data-testtype') && row) {
            const testType = row.getAttribute('data-testtype');
            if (testType) cell.setAttribute('data-testtype', testType);
        }
        if (!cell.getAttribute('data-product') && row) {
            const product = row.getAttribute('data-product');
            if (product) cell.setAttribute('data-product', product);
        }
    });
}

function editNote(cell) {
    console.log('✏️ editNote called');
    
    if (cell.classList.contains('editing')) {
        console.log('⚠️ Already editing');
        return;
    }
    
    const row = cell.closest('tr');
    const autoID = cell.getAttribute('data-autoid') || row.getAttribute('data-autoid');
    const testType = cell.getAttribute('data-testtype') || row.getAttribute('data-testtype');
    const product = cell.getAttribute('data-product') || row.getAttribute('data-product');
    
    console.log('📋 Edit data:', { autoID, testType, product });
    
    if (!autoID || !testType || !product) {
        console.error('❌ Missing required attributes:', { autoID, testType, product });
        alert('Error: Missing required data for editing');
        return;
    }
    
    const currentText = cell.innerText.trim();
    
    const input = document.createElement('textarea');
    input.value = currentText;
    input.style.width = '100%';
    input.style.minHeight = '60px';
    input.style.fontFamily = 'inherit';
    input.style.fontSize = 'inherit';
    input.style.padding = '8px';
    input.style.border = '2px solid #007bff';
    input.style.borderRadius = '4px';
    input.style.boxSizing = 'border-box';
    
    cell.classList.add('editing');
    cell.innerHTML = '';
    cell.appendChild(input);
    
    input.focus();
    input.select();
    
    console.log('✍️ Textarea created and focused');
    
    const saveNote = () => {
        const newText = input.value.trim();
        
        console.log('💾 Saving note:', newText);
        
        fetch('update_notes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'AutoID=' + encodeURIComponent(autoID) + 
                  '&Testtype=' + encodeURIComponent(testType) + 
                  '&Product=' + encodeURIComponent(product) + 
                  '&Notes=' + encodeURIComponent(newText)
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                // Afficher le texte sauvegardé
                cell.classList.remove('editing');
                if (newText) {
                    cell.innerHTML = '<small>' + escapeHtml(newText) + '</small>';
                } else {
                    cell.innerHTML = '';
                }
                
                // Re-colorizer les notes
                setTimeout(() => {
                    colorizeNotesWithText();
                }, 100);
                
                // Animation de feedback
                cell.style.backgroundColor = '#d4edda';
                console.log('✅ Note saved successfully');
                
                setTimeout(() => {
                    cell.style.backgroundColor = '';
                }, 500);
            } else {
                console.error('Save failed:', data.message);
                alert('Error saving note: ' + data.message);
                restoreNote();
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error saving note: ' + error.message);
            restoreNote();
        });
    };
    
    const restoreNote = () => {
        console.log('↩️ Restoring original note');
        cell.classList.remove('editing');
        if (currentText) {
            cell.innerHTML = '<small>' + escapeHtml(currentText) + '</small>';
        } else {
            cell.innerHTML = '';
        }
    };
    
    input.addEventListener('blur', saveNote);
    
    input.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            console.log('⌨️ Ctrl+S detected');
            saveNote();
        }
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            console.log('⎋ Escape detected');
            restoreNote();
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ═══════════════════════════════════════════════════════════════
// 2. LAZY LOADING DES IMAGES
// ═══════════════════════════════════════════════════════════════

function initLazyLoad() {
    console.log('🖼️ initLazyLoad called');
    
    if (!('IntersectionObserver' in window)) {
        console.log('⚠️ IntersectionObserver not supported');
        document.querySelectorAll('img[data-src]').forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
        return;
    }

    const observerOptions = {
        root: null,
        rootMargin: '50px',
        threshold: 0
    };

    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                
                if (img.dataset.srcDesktop && window.innerWidth >= 768) {
                    img.src = img.dataset.srcDesktop;
                } else if (img.dataset.src) {
                    img.src = img.dataset.src;
                }
                
                img.classList.add('lazy-loaded');
                observer.unobserve(img);
                console.log('📸 Image loaded:', img.src);
            }
        });
    }, observerOptions);

    const images = document.querySelectorAll('img[data-src]');
    console.log('Found lazy images:', images.length);
    images.forEach(img => {
        imageObserver.observe(img);
    });

    const bgObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const src = el.dataset.src;
                
                if (src) {
                    el.style.backgroundImage = "url('" + src + "')";
                    el.classList.add('lazy-loaded');
                    bgObserver.unobserve(el);
                    console.log('🎨 Background image loaded');
                }
            }
        });
    }, observerOptions);

    const bgElements = document.querySelectorAll('.lazy-bg[data-src]');
    console.log('Found lazy background elements:', bgElements.length);
    bgElements.forEach(el => {
        bgObserver.observe(el);
    });
}

// ═══════════════════════════════════════════════════════════════
// 3. STICKY HEADERS & SUMMARY
// ═══════════════════════════════════════════════════════════════

function initStickyElements() {
    console.log('📌 initStickyElements called');
    
    const header = document.querySelector('.header');
    const stats = document.querySelector('.stats');
    
    if (!header && !stats) {
        console.log('⚠️ No sticky elements found');
        return;
    }
    
    const observerOptions = {
        threshold: 0,
        rootMargin: '-1px 0px 0px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) {
                entry.target.classList.add('sticky-shadow');
                console.log('✨ Sticky shadow added');
            } else {
                entry.target.classList.remove('sticky-shadow');
            }
        });
    }, observerOptions);
    
    if (header) observer.observe(header);
    if (stats) observer.observe(stats);
    
    console.log('✅ Sticky elements initialized');
}

// ═══════════════════════════════════════════════════════════════
// 4. VALIDATIONS & MISES À JOUR AJAX
// ═══════════════════════════════════════════════════════════════

function updateValidation(autoID, isChecked, testType, product) {
    const url = 'update_validation.php?AutoID=' + autoID + 
                '&Checked=' + (isChecked ? '1' : '0') + 
                '&Testtype=' + encodeURIComponent(testType) + 
                '&Product=' + encodeURIComponent(product);
    
    console.log('🔄 updateValidation:', { autoID, isChecked, testType, product });
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Validation updated successfully');
            } else {
                console.error('❌ Error:', data.message);
                alert('Error updating validation: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error updating validation');
        });
}

function updateScenarioValidation(autoID, isChecked, testType, product) {
    console.log('🔄 updateScenarioValidation:', { autoID, isChecked, testType, product });
    
    fetch('update_validation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'AutoID=' + encodeURIComponent(autoID) + 
              '&Checked=' + (isChecked ? '1' : '0') + 
              '&TestType=' + encodeURIComponent(testType) + 
              '&Product=' + encodeURIComponent(product)
    })
        .then(response => {
            console.log('📡 Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('📝 Response text:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    console.log('✅ Validation updated successfully');
                    updateResultDisplay(autoID, isChecked);
                    updateTestSetStats();
                } else {
                    console.error('❌ Server error:', data.error);
                    alert('Error updating validation: ' + (data.error || data.message));
                }
            } catch (e) {
                console.error('❌ JSON parse error:', e);
                console.error('📝 Raw response:', text);
                alert('Server error: ' + text.substring(0, 100));
            }
        })
        .catch(error => {
            console.error('❌ Fetch error:', error);
            alert('Error updating validation: ' + error.message);
        });
}

function updateResultDisplay(autoID, isChecked) {
    const row = document.querySelector('tr[data-autoid="' + autoID + '"]');
    if (!row) {
        console.log('⚠️ Row not found:', autoID);
        return;
    }
    
    const resultCell = row.querySelector('.result-cell');
    if (!resultCell) {
        console.log('⚠️ Result cell not found');
        return;
    }
    
    console.log('🔄 Updating result display for:', autoID);
    
    // Déterminer l'ancien statut en lisant le HTML actuel
    const currentHTML = resultCell.innerHTML;
    let wasFlaky = currentHTML.includes('result-flaky') || currentHTML.includes('Flaky');
    let wasFailed = currentHTML.includes('result-failed') || currentHTML.includes('Failed');
    
    console.log('🔍 Ancien statut du HTML:', { autoID, wasFlaky, wasFailed, currentHTML: currentHTML.substring(0, 50), isChecked });
    
    if (isChecked) {
        resultCell.innerHTML = '<span class="result-badge result-flaky">⚠️ Flaky</span>';
        
        // Si c'était Failed avant → passer en Flaky
        if (wasFailed && !wasFlaky) {
            console.log('✅ Détecté: Failed → Flaky, appel updateCounters(+1, -1)');
            updateCounters(+1, -1);  // +1 Flaky, -1 Failed
        } else {
            console.log('⚠️ Statut inchangé ou déjà Flaky');
        }
    } else {
        resultCell.innerHTML = '<span class="result-badge result-failed">❌ Failed</span>';
        
        // Si c'était Flaky avant → passer en Failed
        if (wasFlaky && !wasFailed) {
            console.log('✅ Détecté: Flaky → Failed, appel updateCounters(-1, +1)');
            updateCounters(-1, +1);  // -1 Flaky, +1 Failed
        } else {
            console.log('⚠️ Statut inchangé ou déjà Failed');
        }
    }
}

function updateCounters(flakyDelta, failedDelta) {
    const testsetInfo = document.querySelector('.testset-info');
    if (!testsetInfo) return;
    
    const statusFlaky = testsetInfo.querySelector('span.status-flaky');
    const statusFailed = testsetInfo.querySelector('span.status-failed');
    
    if (!statusFlaky || !statusFailed) return;
    
    // Lire les valeurs actuelles
    const flakyText = statusFlaky.textContent.trim();
    const failedText = statusFailed.textContent.trim();
    
    let flaky = parseInt(flakyText.replace(/\D/g, '')) || 0;
    let failed = parseInt(failedText.replace(/\D/g, '')) || 0;
    
    // Appliquer les deltas
    flaky += flakyDelta;
    failed += failedDelta;
    
    // Mettre à jour l'affichage
    statusFlaky.textContent = '⚠️ ' + flaky;
    statusFailed.textContent = '❌ ' + failed;
    
    console.log('✅ Compteurs mis à jour:', { flaky, failed });
}

function updateScenarioManual(autoID, isChecked, testType, product) {
    const url = 'update_manual.php?AutoID=' + autoID + 
                '&Manual=' + (isChecked ? '1' : '0') + 
                '&Testtype=' + encodeURIComponent(testType) + 
                '&Product=' + encodeURIComponent(product);
    
    console.log('🔄 updateScenarioManual:', { autoID, isChecked, testType, product });
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Manual flag updated successfully');
            } else {
                console.error('❌ Error:', data.message);
                alert('Error updating manual flag: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error updating manual flag');
        });
}

// ═══════════════════════════════════════════════════════════════
// INITIALISATION PRINCIPALE
// ═══════════════════════════════════════════════════════════════

console.log('🚀 App.js loaded');

function updateTestSetStats() {
    console.log('🎯 updateTestSetStats called!');
    
    // Récupérer l'AutoID du TestSet depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const autoID = urlParams.get('AutoID');
    
    if (!autoID) {
        console.error('❌ AutoID du TestSet non trouvé');
        return;
    }
    
    // Lire les valeurs actuellement affichées
    const testsetInfo = document.querySelector('.testset-info');
    if (!testsetInfo) {
        console.error('❌ testset-info non trouvé');
        return;
    }
    
    const statusFlaky = testsetInfo.querySelector('span.status-flaky');
    const statusFailed = testsetInfo.querySelector('span.status-failed');
    
    if (!statusFlaky || !statusFailed) {
        console.error('❌ Status spans non trouvés');
        return;
    }
    
    // Extraire les valeurs actuelles
    const flakyText = statusFlaky.textContent.trim();
    const failedText = statusFailed.textContent.trim();
    
    let flaky = parseInt(flakyText.replace(/\D/g, '')) || 0;
    let failed = parseInt(failedText.replace(/\D/g, '')) || 0;
    
    console.log('📈 Valeurs actuelles affichées:', { flaky, failed });
    console.log('📡 Saving stats to database for AutoID:', autoID);
    
    // Envoyer seulement Flaky et Failed
    // PHP calculera Passed = TotalCount - Flaky - Failed
    fetch('update_testset_stats.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'AutoID=' + encodeURIComponent(autoID) + 
              '&Flaky=' + flaky + 
              '&Failed=' + failed
    })
        .then(response => response.text())
        .then(text => {
            console.log('📝 Response:', text);
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    console.log('✅ TestSet stats saved successfully:', data.stats);
                } else {
                    console.error('❌ Error saving stats:', data.error);
                }
            } catch (e) {
                console.error('❌ JSON parse error:', e);
            }
        })
        .catch(error => {
            console.error('❌ Error saving stats:', error);
        });
}

function colorizeNotesWithText() {
    const notesCells = document.querySelectorAll('.notes-cell');
    notesCells.forEach(cell => {
        const text = cell.textContent.trim();
        if (text.length > 0) {
            cell.classList.add('has-notes');
            // Forcer avec style inline si classe ne fonctionne pas
            const isDarkMode = document.body.classList.contains('dark-mode');
            cell.style.backgroundColor = isDarkMode ? '#1a3a52' : '#e3f2fd';
            console.log('✅ Notes colorisée:', text.substring(0, 30));
        }
    });
}

function initApp() {
    console.log('🎬 Initializing app...');
    colorizeNotesWithText();
    initTableSorting();
    initEditableNotes();
    initLazyLoad();
    initStickyElements();
    console.log('✅ App initialized successfully');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
    console.log('⏳ Waiting for DOMContentLoaded');
} else {
    initApp();
    console.log('✅ DOM already loaded');
}

if (window.addEventListener) {
    window.addEventListener('load', () => {
        console.log('🔄 Page fully loaded');
        initEditableNotes();
    });
}