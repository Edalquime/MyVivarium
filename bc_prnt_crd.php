<body>

<div class="page-container">
    <?php foreach ($breedingcages as $breedingcage) : ?>
        <div class="card-slot">
            
            <table class="actual-card">
                <tr>
                    <td colspan="5" style="text-align:center; height: 30px; vertical-align:middle; background-color: #f2f2f2;">
                        <span class="title-span">JAULA DE CRUCE # <?= htmlspecialchars($breedingcage["cage_id"]) ?></span>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="width: 40%;">
                        <span class="label-span">PI:</span> 
                        <span class="value-span"><?= htmlspecialchars($breedingcage["pi_name"] ?? 'N/A'); ?></span>
                    </td>
                    <td colspan="2" style="width: 40%;">
                        <span class="label-span">Cepa:</span> 
                        <span class="value-span">
                            <?php 
                                $strainDisplay = htmlspecialchars($breedingcage['str_name'] ?? 'N/A');
                                if(!empty($breedingcage['str_aka'])) {
                                    $strainDisplay .= " [" . htmlspecialchars($breedingcage['str_aka']) . "]";
                                }
                                echo $strainDisplay;
                            ?>
                        </span>
                    </td>
                    <td rowspan="4" style="width: 20%; text-align:center; vertical-align:middle; padding: 5px;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=https://<?= $url ?>/bc_view.php?id=<?= $breedingcage["cage_id"] ?>&choe=UTF-8" alt="QR" style="display:block; margin: 0 auto; max-width: 100%; height: auto;">
                    </td>
                </tr>

                <tr>
                    <td colspan="2">
                        <span class="label-span">IACUC:</span> 
                        <span class="value-span"><?= htmlspecialchars(getIacucIdsByCageId($con, $breedingcage['cage_id'])); ?></span>
                    </td>
                    <td colspan="2">
                        <span class="label-span">Fecha Cruce:</span> 
                        <span class="value-span"><?= htmlspecialchars($breedingcage["cross"]); ?></span>
                    </td>
                </tr>

                <tr>
                    <td colspan="2">
                        <span class="label-span">Teléfono:</span> 
                        <span class="value-span" style="font-size: 7pt;"><?= htmlspecialchars($breedingcage["contact_phone"]) ?></span>
                    </td>
                    <td colspan="2">
                        <span class="label-span">Email:</span> 
                        <span class="value-span" style="font-size: 6.5pt; word-break: break-all;"><?= htmlspecialchars($breedingcage["contact_email"]) ?></span>
                    </td>
                </tr>

                <tr>
                    <td colspan="2">
                        <span class="label-span">ID Macho (<?= $breedingcage["male_n"] ?? 1 ?>):</span> 
                        <span class="value-span"><?= htmlspecialchars($breedingcage["male_id"]) ?></span><br>
                        <span class="label-span">Nac:</span> <span class="value-span"><?= htmlspecialchars($breedingcage["male_dob"]) ?></span>
                    </td>
                    <td colspan="2">
                        <span class="label-span">ID Hembra (<?= $breedingcage["female_n"] ?? 1 ?>):</span> 
                        <span class="value-span"><?= htmlspecialchars($breedingcage["female_id"]) ?></span><br>
                        <span class="label-span">Nac:</span> <span class="value-span"><?= htmlspecialchars($breedingcage["female_dob"]) ?></span>
                    </td>
                </tr>

                <tr style="background-color: #f2f2f2; font-weight: bold; text-align: center; vertical-align: middle;">
                    <td style="width: 32%; height: 25px;"><span class="label-span">Fecha Nac.</span></td>
                    <td style="width: 17%;"><span class="label-span">Vivos</span></td>
                    <td style="width: 17%;"><span class="label-span">Muertos</span></td>
                    <td style="width: 17%;"><span class="label-span">Machos</span></td>
                    <td style="width: 17%;"><span class="label-span">Hembras</span></td>
                </tr>

                <?php for ($i = 0; $i < 5; $i++) : ?>
                    <tr style="text-align: center; vertical-align: middle;">
                        <td style="height: 23px;"><?= isset($breedingcage['litters'][$i]['litter_dob']) ? htmlspecialchars($breedingcage['litters'][$i]['litter_dob']) : '' ?></td>
                        <td><?= isset($breedingcage['litters'][$i]['pups_alive']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_alive']) : '' ?></td>
                        <td><?= isset($breedingcage['litters'][$i]['pups_dead']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_dead']) : '' ?></td>
                        <td><?= isset($breedingcage['litters'][$i]['pups_male']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_male']) : '' ?></td>
                        <td><?= isset($breedingcage['litters'][$i]['pups_female']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_female']) : '' ?></td>
                    </tr>
                <?php endfor; ?>
            </table>

        </div>
    <?php endforeach; ?>
</div>

</body>
