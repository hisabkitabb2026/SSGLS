"""Transport API routes."""
from fastapi import APIRouter, Depends, HTTPException, status, Query
from sqlalchemy.orm import Session
from typing import List
from config.database import get_db
from app.middleware import get_current_user
from app.services import TransportService
from app.schemas import TransportResponse, TransportCreate, TransportUpdate, ErrorResponse
from app.metrics import http_requests_total, transport_operations_total
from config.logging_config import logger

router = APIRouter(prefix="/api/v1/transports", tags=["transports"])


@router.post(
    "",
    response_model=TransportResponse,
    status_code=status.HTTP_201_CREATED,
    responses={
        400: {"model": ErrorResponse},
        401: {"model": ErrorResponse},
        500: {"model": ErrorResponse}
    }
)
async def create_transport(
    transport_data: TransportCreate,
    db: Session = Depends(get_db),
    current_user: dict = Depends(get_current_user)
):
    """Create a new transport record."""
    try:
        company_id = current_user["company_id"]
        service = TransportService(db)

        transport = service.create_transport(company_id, transport_data)

        http_requests_total.labels(
            method="POST",
            endpoint="/transports",
            status_code=201
        ).inc()
        transport_operations_total.labels(
            operation="create",
            status="success"
        ).inc()

        logger.info(f"Created transport {transport.id} for company {company_id}")
        return transport

    except ValueError as e:
        http_requests_total.labels(
            method="POST",
            endpoint="/transports",
            status_code=400
        ).inc()
        transport_operations_total.labels(
            operation="create",
            status="error"
        ).inc()
        logger.warning(f"Validation error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )
    except Exception as e:
        http_requests_total.labels(
            method="POST",
            endpoint="/transports",
            status_code=500
        ).inc()
        transport_operations_total.labels(
            operation="create",
            status="error"
        ).inc()
        logger.error(f"Error creating transport: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("", response_model=dict)
async def list_transports(
    db: Session = Depends(get_db),
    current_user: dict = Depends(get_current_user),
    skip: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=1000),
    status: str = Query(None),
    is_active: bool = Query(True)
):
    """List transports with optional filtering."""
    try:
        company_id = current_user["company_id"]
        service = TransportService(db)

        transports, total = service.list_transports(
            company_id,
            skip=skip,
            limit=limit,
            status=status,
            is_active=is_active
        )

        http_requests_total.labels(
            method="GET",
            endpoint="/transports",
            status_code=200
        ).inc()

        return {
            "data": [t.to_dict() for t in transports],
            "total": total,
            "skip": skip,
            "limit": limit
        }

    except Exception as e:
        http_requests_total.labels(
            method="GET",
            endpoint="/transports",
            status_code=500
        ).inc()
        logger.error(f"Error listing transports: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/{transport_id}", response_model=TransportResponse)
async def get_transport(
    transport_id: int,
    db: Session = Depends(get_db),
    current_user: dict = Depends(get_current_user)
):
    """Get a specific transport by ID."""
    try:
        company_id = current_user["company_id"]
        service = TransportService(db)

        transport = service.get_transport(company_id, transport_id)

        if not transport:
            http_requests_total.labels(
                method="GET",
                endpoint="/transports/{id}",
                status_code=404
            ).inc()
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Transport {transport_id} not found"
            )

        http_requests_total.labels(
            method="GET",
            endpoint="/transports/{id}",
            status_code=200
        ).inc()

        return transport

    except HTTPException:
        raise
    except Exception as e:
        http_requests_total.labels(
            method="GET",
            endpoint="/transports/{id}",
            status_code=500
        ).inc()
        logger.error(f"Error getting transport: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.put("/{transport_id}", response_model=TransportResponse)
async def update_transport(
    transport_id: int,
    update_data: TransportUpdate,
    db: Session = Depends(get_db),
    current_user: dict = Depends(get_current_user)
):
    """Update a transport record."""
    try:
        company_id = current_user["company_id"]
        service = TransportService(db)

        transport = service.update_transport(company_id, transport_id, update_data)

        if not transport:
            http_requests_total.labels(
                method="PUT",
                endpoint="/transports/{id}",
                status_code=404
            ).inc()
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Transport {transport_id} not found"
            )

        http_requests_total.labels(
            method="PUT",
            endpoint="/transports/{id}",
            status_code=200
        ).inc()
        transport_operations_total.labels(
            operation="update",
            status="success"
        ).inc()

        logger.info(f"Updated transport {transport_id}")
        return transport

    except HTTPException:
        raise
    except Exception as e:
        http_requests_total.labels(
            method="PUT",
            endpoint="/transports/{id}",
            status_code=500
        ).inc()
        transport_operations_total.labels(
            operation="update",
            status="error"
        ).inc()
        logger.error(f"Error updating transport: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.delete("/{transport_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_transport(
    transport_id: int,
    db: Session = Depends(get_db),
    current_user: dict = Depends(get_current_user)
):
    """Delete (soft delete) a transport record."""
    try:
        company_id = current_user["company_id"]
        service = TransportService(db)

        deleted = service.delete_transport(company_id, transport_id, soft_delete=True)

        if not deleted:
            http_requests_total.labels(
                method="DELETE",
                endpoint="/transports/{id}",
                status_code=404
            ).inc()
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Transport {transport_id} not found"
            )

        http_requests_total.labels(
            method="DELETE",
            endpoint="/transports/{id}",
            status_code=204
        ).inc()
        transport_operations_total.labels(
            operation="delete",
            status="success"
        ).inc()

        logger.info(f"Deleted transport {transport_id}")

    except HTTPException:
        raise
    except Exception as e:
        http_requests_total.labels(
            method="DELETE",
            endpoint="/transports/{id}",
            status_code=500
        ).inc()
        transport_operations_total.labels(
            operation="delete",
            status="error"
        ).inc()
        logger.error(f"Error deleting transport: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )


@router.get("/{transport_id}/statistics", response_model=dict)
async def get_statistics(
    db: Session = Depends(get_db),
    current_user: dict = Depends(get_current_user)
):
    """Get transport statistics for the company."""
    try:
        company_id = current_user["company_id"]
        service = TransportService(db)

        stats = service.get_statistics(company_id)

        http_requests_total.labels(
            method="GET",
            endpoint="/transports/statistics",
            status_code=200
        ).inc()

        return stats

    except Exception as e:
        http_requests_total.labels(
            method="GET",
            endpoint="/transports/statistics",
            status_code=500
        ).inc()
        logger.error(f"Error getting statistics: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error"
        )
