insert into blg_buyer_rechnung (`key`,val,id)

with x as (
select 
    h.id,adr.* 

from blg_hdr_rechnung h 
    join blg_adressen_rechnung a
        on h.id =a.id
    join adressen adr
        on (a.kundennummer,a.kostenstelle) = (adr.kundennummer,adr.kostenstelle)
    join adressen_electronic_scheme eas
        on (adr.kundennummer, adr.kostenstelle) = (eas.kundennummer, eas.kostenstelle)
        and eas.electronic_scheme = 'EM'
where h.id not in (select id from blg_buyer_rechnung)
)
/*

line3
line2
line1

*/
select 'line1' `key`, name val, id from x
union
select 'line2' `key`, zusatz val, id from x
union
select 'line3' `key`, strasse val, id from x
union
select 'postcode' `key`, plz val, id from x
union 
select 'city' `key`, ort val, id from x
union 
select 'city' `key`, ort val, id from x
union
select 'electronic_address' `key`, electronic_address val, id from x
union
select 'electronic_address_scheme' `key`, electronic_scheme val, id from x


