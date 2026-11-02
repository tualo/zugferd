create or replace view view_blg_pos_rechnung_en16931 as 
select 
    gruppierung,
    beleg,

    rank() over (
        PARTITION BY beleg
        ORDER BY pos
    ) position,

    FIRST_VALUE(artikel) OVER (
        PARTITION BY beleg,gruppierung
        ORDER BY pos
    ) article_id,
    
    a.langtext article_text,
    max(anzahl) amount,
    sum(netto) / max(anzahl) singleprice,
    round( max(anzahl) * (sum(netto) / max(anzahl)), 2 )  net,
    round( max(anzahl) * (sum(netto) / max(anzahl)) ,2 )*(1+steuersatz/100)  gross,
    steuersatz tax_rate,
    round( max(anzahl) * (sum(netto) / max(anzahl)) ,2 )*(1+steuersatz/100) - round( max(anzahl) * (sum(netto) / max(anzahl)),2 )  tax_value,
    
    m.code unit_code
    

from 
    blg_pos_rechnung p
    join artikelgruppen  a 
        on  a.gruppen_id = p.artikel
--            and beleg = '262332'
    left join mengeneinheiten m
        on m.id = a.einheit
group by gruppierung,steuersatz