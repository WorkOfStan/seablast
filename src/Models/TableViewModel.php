<?php

// GET:
// SELECT * ... explicitně uvést * v konfiguraci kvůli security; také vyjmenovat editable fields
// FROM ___ ... conf table
// WHERE xy ... conf filter
// ORDER BY id/timestamp DESC ... defaultně id nebo conf
// LIMIT offset ... zacit 1-20
// todo podporovat číselníky, tedy vazby mezi tabulkama

// POST a DELETE přes API (todo CSRF) TableUpdateApi /api/table
// UPDATE jen pokud editable field
