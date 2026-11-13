<div class="row">
    <div class="col-md-12 col-sm-12">
        <div class="portlet light bordered">
            <div class="portlet-title">
                <div class="caption">
                    <i class="icon-share font-dark hide"></i>
                    <span class="caption-subject font-dark bold uppercase">EGA datasets</span>
                </div>
            </div>

            <div class="portlet-body" id="portlet-ws">
                <div class="row">
                    <div class="col-md-12">
                        <table id="datasets-list" class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Dataset ID</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Dataset Types</th>
                                    <th>Technologies</th>
                                    <th>Number of Samples</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dataArray as $item): ?>
                                    <tr class="dataset-row"
                                        data-accession-id="<?php echo htmlspecialchars($item['accession_id']); ?>">
                                        <td><?php echo htmlspecialchars($item['accession_id']); ?></td>
                                        <td><?php echo htmlspecialchars($item['title'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td><?php echo htmlspecialchars(implode(', ', $item['dataset_types'] ?? ['N/A'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(implode(', ', $item['technologies'] ?? ['N/A'])); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['num_samples']); ?></td>
                                    </tr>
                                    <tr class="file-list-row" style="display: none;">
                                        <td colspan="11">
                                            <div class="file-list-container">
                                                <table class="table table-striped table-bordered table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>File ID</th>
                                                            <th>Display name</th>
                                                            <th>Filesize</th>
                                                            <th>Extension</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="file-list-body">
                                                        <!-- Files will be loaded here -->
                                                    </tbody>
                                                </table>
                                                <div class="file-pagination">
                                                    <!-- Pagination controls will be added here -->
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <?php $egaPageUrl = "getdata/uploadForm.php?page="; ?>
                    <?php if ($currentPage > 1): ?>
                        <a href="<?php echo $egaPageUrl . ($currentPage - 1); ?>#load_from_ega">&laquo; Previous</a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $currentPage - 10);
                    $end_page = $total_pages;

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="<?php echo $egaPageUrl . $i; ?>#load_from_ega"
                            class="<?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($hasNextPage): ?>
                        <a href="<?php echo $egaPageUrl . ($currentPage + 1); ?>#load_from_ega">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <div id="add-to-workspace-container" style="margin-top: 20px;">
                <button id="add-to-workspace">Add to workspace</button>
            </div>
        </div>
        <!-- END EXAMPLE TABLE PORTLET-->
    </div>
    <!-- END CONTENT BODY -->
</div>
<!-- END CONTENT -->


<script>
    // Reopen the EGA tab after following a datasets pagination link
    window.addEventListener('load', function () {
        if (window.location.hash === '#load_from_ega' && window.jQuery) {
            jQuery('.nav-tabs a[href="#load_from_ega"]').tab('show');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelectorAll('.dataset-row');
        rows.forEach(row => {
            row.addEventListener('click', function () {
                const accessionId = this.getAttribute('data-accession-id');
                const fileListRow = this.nextElementSibling;
                const fileListContainer = fileListRow.querySelector('.file-list-container');
                const fileListBody = fileListContainer.querySelector('.file-list-body');
                const filePagination = fileListContainer.querySelector('.file-pagination');
                fileListBody.innerHTML = ''; // Clear previous file list
                filePagination.innerHTML = ''; // Clear previous pagination

                fetchFiles(accessionId, 0, fileListBody, filePagination);
                fileListRow.style.display = 'table-row'; // Show the file list row
            });
        });
    });

    const filesPerPage = 10;

    function fetchFiles(accessionId, offset, fileListBody, filePagination) {
        // One extra file is requested to know whether there is a next page
        fetch(`getdata/fetchEgaDatasets.php?action=fetch_files&accession_id=${accessionId}&offset=${offset}&limit=${filesPerPage + 1}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                fileListBody.innerHTML = ''; // Clear previous file list
                const hasNextFilesPage = data.length > filesPerPage;
                data = data.slice(0, filesPerPage);
                egaLocations = ['crg', 'spain'];
                data.filter(file => file.locations.some(location => egaLocations.includes(location))).forEach(file => {
                    const row = document.createElement('tr');
                    row.dataset.datasetId = accessionId;
                    row.dataset.fileId = file.accession_id;
                    row.dataset.displayName = file.display_name;
                    row.dataset.fileSize = file.filesize;

                    // Convert filesize from bytes to MB or GB
                    const filesize = file.filesize;
                    const displaySize = filesize > 1e9 
                        ? (filesize / 1e9).toFixed(2) + ' GB' 
                        : filesize > 1e6 
                            ? (filesize / 1e6).toFixed(2) + ' MB' 
                            : (filesize / 1e3).toFixed(2) + ' KB';

                    row.innerHTML = `
                    <td><input type="checkbox" class="file-select"></td>
                    <td>${file.accession_id}</td>
                    <td>${file.display_name}</td>
                    <td>${displaySize}</td>
                    <td>${file.extension}</td>
                `;

                    fileListBody.appendChild(row);
                });

                // Add pagination controls
                filePagination.innerHTML = '';

                if (offset > 0) {
                    const prevButton = document.createElement('button');
                    prevButton.innerText = 'Previous';
                    prevButton.addEventListener('click', () => fetchFiles(accessionId, offset - filesPerPage, fileListBody, filePagination));
                    filePagination.appendChild(prevButton);
                }

                if (hasNextFilesPage) {
                    const nextButton = document.createElement('button');
                    nextButton.innerText = 'Next';
                    nextButton.addEventListener('click', () => fetchFiles(accessionId, offset + filesPerPage, fileListBody, filePagination));
                    filePagination.appendChild(nextButton);
                }
            })
            .catch(error => {
                alert('Error fetching files: ' + error);
            });
    }

    document.getElementById('add-to-workspace').addEventListener('click', function () {
        const selectedFiles = [];
        document.querySelectorAll('.file-select:checked').forEach(checkbox => {
            const row = checkbox.closest('tr');
            const { datasetId, fileId, displayName, fileSize } = row.dataset;

            selectedFiles.push({
                remote_path: `${datasetId}/${displayName}`,
                name: displayName,
                size: Number(fileSize) || 0,
                meta: { dataset_id: datasetId, file_id: fileId }
            });
        });

        if (selectedFiles.length === 0) {
            alert('No files selected');
            return;
        }

        addFilesToWorkspace(selectedFiles);
    });

    function addFilesToWorkspace(files) {
        const body = new FormData();
        body.append('uploadType', 'remote');
        body.append('source', 'EGA');
        body.append('files', JSON.stringify(files));

        fetch('applib/getData.php', { method: 'POST', body }).then(async response => {
            const data = await response.json().catch(() => null);
            if (!response.ok) {
                throw new Error((data && data.error) || 'Network response was not ok');
            }

            return data;
        }).then(data => {
            const uploadedIds = (data && data.fileIds) || [];
            if (uploadedIds.length === 0) {
                throw new Error('No files were added to the workspace');
            }

            // Ask the user for the files metadata
            const query = uploadedIds.map(id => 'fn[]=' + encodeURIComponent(id)).join('&');
            location.href = "getdata/uploadForm2.php?" + query;
        }).catch(error => {
            console.error('Error:', error);
            alert('Error adding files to the workspace: ' + error.message);
        });
    }

</script>

