
alter table buchungskreise add if not exists electronic_scheme varchar(10);
alter table buchungskreise add if not exists electronic_address varchar(255);
alter table buchungskreise add if not exists contact_name varchar(255);
alter table buchungskreise add if not exists contact_phone varchar(255);
alter table buchungskreise add if not exists contact_email varchar(255);


CREATE OR REPLACE TRIGGER blg_hdr_rechnung_au_seller_information
AFTER UPDATE ON blg_hdr_rechnung FOR EACH ROW
BEGIN
    for record in (
        select 
            id,
            name,
            vat_id,
            tax_id,
            firmen_name,
            firmen_strasse,
            firmen_plz,
            firmen_ort,

            electronic_scheme,
            electronic_address,
            contact_name,
            contact_phone,
            contact_email


        from 
            buchungskreise 
        where id = new.buchungskreis
     ) do


        if (record.vat_id<>"") then
            insert ignore into blg_taxregistration_rechnung (
                `id`,
                `key`,
                `val`
            ) values
            (
                new.id,
                'VA',
                record.vat_id
            );
        end if;


        if (record.tax_id<>"") then
            insert ignore into blg_taxregistration_rechnung (
                `id`,
                `key`,
                `val`
            ) values
            (
                new.id,
                'FC',
                record.tax_id
            );
        end if;

        insert ignore into blg_seller_rechnung (
            `id`,
            `key`,
            `val`
        ) values
        (
            new.id,
            'line1',
            record.firmen_name
        ),
        (
            new.id,
            'line3',
            record.firmen_strasse
        ),
        (
            new.id,
            'postcode',
            record.firmen_plz
        ),
        (
            new.id,
            'city',
            record.firmen_ort
        ),
        (
            new.id,
            'electronic_scheme',
            record.electronic_scheme
        ),
        (
            new.id,
            'electronic_address',
            record.electronic_address
        ),
        (
            new.id,
            'contact_name',
            record.contact_name
        ),
        (
            new.id,
            'contact_phone',
            record.contact_phone
        ),
        (
            new.id,
            'contact_email',
            record.contact_email
        );

     end for;
END //